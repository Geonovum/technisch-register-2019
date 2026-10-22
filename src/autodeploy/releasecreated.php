<?php
/*
MIT License

Copyright (c) 2018-2019 Thijs Brentjens (thijs@brentjensgeoict.nl), for Geonovum The Netherlands

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

/*
PHP script to receive a release of a Github Webhook and copy relevant files and directories for Geonovum technisch register artefacts.
*/

// get settings
include 'utils.php';
include 'registerConfig.php';

// PHP errors and warnings go to the error log, never into the response
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Prevent accidental XSS
header('Content-type: text/plain');

// GitHub's id of this delivery, to match log lines with Recent Deliveries
$deliveryId = isset($_SERVER['HTTP_X_GITHUB_DELIVERY']) ? substr(preg_replace('/[^A-Za-z0-9-]/', '', $_SERVER['HTTP_X_GITHUB_DELIVERY']), 0, 64) : '-';

function webhookLog($message)
{
    global $webhookLogFile, $deliveryId;
    $remote = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '-';
    // the message can contain values from the request: keep it on one line and bounded
    $message = substr(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $message), 0, 4000);
    $line = date('c').' '.$remote.' '.$deliveryId.' '.$message."\n";
    if (@file_put_contents($webhookLogFile, $line, FILE_APPEND | LOCK_EX) === false) {
        error_log('technisch-register webhook: '.trim($line));
    }
}

// Send the response for GitHub (shown under Recent Deliveries), log it and stop.
// $logDetail is only written to the local log.
function respond($status, $message, $logDetail = '')
{
    http_response_code($status);
    echo $message;
    webhookLog($status.' '.$message.($logDetail === '' ? '' : ' | '.$logDetail));
    exit;
}

// Extract only files inside artefact directories, and only of an allowed type, into $targetDir.
// The archive is not trusted to be well-formed: entries with unsafe paths, links, hidden files
// and other file types are skipped. Returns the skipped files as "path (reason)".
function extractArtefacts($zip, $targetDir, $descriptions, $allowedExtensions)
{
    // never extract server-side executable types, also not as an inner extension (x.php.xsd)
    $executableExtensions = array('php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phar', 'phps',
        'shtml', 'asp', 'aspx', 'jsp', 'cgi', 'pl', 'py', 'sh');
    $skipped = array();
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        // GitHub archives contain one top-level directory: <repo>-<tag>/<path in the repository>
        $parts = explode('/', $name);
        if (count($parts) < 3 || !isset($descriptions[$parts[1]]) || substr($name, -1) === '/') {
            continue;
        }
        $relativePath = implode('/', array_slice($parts, 1));

        $reason = '';
        if (strpos($name, '\\') !== false || strpos($name, "\0") !== false) {
            $reason = 'unsafe path';
        }
        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                $reason = 'unsafe path';
            } elseif ($reason === '' && $part[0] === '.') {
                $reason = 'hidden file or directory';
            }
        }
        $opsys = 0;
        $attributes = 0;
        if ($reason === '' && $zip->getExternalAttributesIndex($i, $opsys, $attributes)
            && $opsys === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000) {
            $reason = 'link';
        }
        if ($reason === '') {
            $segments = explode('.', strtolower(end($parts)));
            $extension = count($segments) > 1 ? end($segments) : '';
            if (!in_array($extension, $allowedExtensions, true)
                || array_intersect(array_slice($segments, 1), $executableExtensions)) {
                $reason = 'file type not allowed';
            }
        }
        if ($reason !== '') {
            $skipped[] = $relativePath.' ('.$reason.')';
            continue;
        }

        $target = $targetDir.'/'.implode('/', $parts);
        if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0775, true)) {
            $skipped[] = $relativePath.' (could not be extracted)';
            continue;
        }
        $in = $zip->getStream($name);
        $out = $in === false ? false : fopen($target, 'wb');
        if ($in === false || $out === false) {
            $skipped[] = $relativePath.' (could not be extracted)';
            continue;
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
    }
    return $skipped;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, "Only POST requests from the GitHub webhook are accepted");
}

// The JSON payload as sent by Github. Release payloads are small: read at most one byte
// more than the limit, so an oversized request cannot use much memory.
$maxPayloadBytes = 2 * 1024 * 1024;
$messageBody = file_get_contents('php://input', false, null, 0, $maxPayloadBytes + 1);
if ($messageBody === false || strlen($messageBody) > $maxPayloadBytes) {
    respond(413, "Payload too large");
}

// Only accept requests signed with the shared webhook secret (header X-Hub-Signature-256).
// The repository URL in the payload is chosen by the sender, so repos.json alone does not
// authenticate a request. Without a configured secret nothing is processed.
$webhookSecret = is_readable($webhookSecretFile) ? trim(file_get_contents($webhookSecretFile)) : '';
if ($webhookSecret === '') {
    respond(500, "Webhook not available", "secret file missing, empty or not readable: ".$webhookSecretFile);
}
// a valid header is exactly 'sha256=' followed by 64 hexadecimal characters
$signature = isset($_SERVER['HTTP_X_HUB_SIGNATURE_256']) ? $_SERVER['HTTP_X_HUB_SIGNATURE_256'] : '';
if (!preg_match('/\Asha256=[0-9a-f]{64}\z/', $signature)
    || !hash_equals('sha256='.hash_hmac('sha256', $messageBody, $webhookSecret), $signature)) {
    respond(401, "NOT SYNCED TO REGISTER. Invalid or missing signature: check the secret of the webhook.");
}

$event = isset($_SERVER['HTTP_X_GITHUB_EVENT']) ? $_SERVER['HTTP_X_GITHUB_EVENT'] : '';
if ($event === 'ping') {
    respond(200, "pong: the webhook and its secret are configured correctly");
}
if ($event !== 'release') {
    respond(200, "Ignored event: ".$event);
}

// process the config of repositories to get all informationmodels that are allowed to publish artefacts
$reposArr = json_decode(file_get_contents($reposURL), true);

function getRepoInfoByURL($reposArr, $repoUrl)
{
    // get the repo information for a model, using the repoURL
    $info = false;
    foreach ($reposArr as $rp) {
        if (strtoupper($rp['url']) == strtoupper($repoUrl)) {
            $info = $rp;
        }
    }
    return $info;
}

// descriptions: types of artefacts
$descriptions = json_decode(file_get_contents($descriptionsURL), true);

if (!is_array($reposArr) || !is_array($descriptions)) {
    respond(502, "NOT SYNCED TO REGISTER. The register configuration could not be read. Retry later.", "could not read or parse ".$reposURL." or ".$descriptionsURL);
}

// process the GitHub release information
// JSON format is used
$evt = json_decode($messageBody);
if (!is_object($evt) || !isset($evt->action, $evt->release, $evt->repository)) {
    respond(400, "NOT SYNCED TO REGISTER. The payload is not a release event in JSON: set the content type of the webhook to application/json.");
}

// GitHub sends several deliveries per release. 'released' is sent once when a release is
// published or a pre-release is changed into a release: that goes to production.
// 'published' is also sent for pre-releases: only those go to staging.
// Other actions, including 'created' (also sent when a draft is saved), are ignored.
$isPrerelease = !empty($evt->release->prerelease);
if ($evt->action == 'released' && !$isPrerelease) {
    $envDir = $baseDir.'/'.$productionDir;
} elseif ($evt->action == 'published' && $isPrerelease) {
    $envDir = $baseDir.'/'.$stagingDir;
} else {
    respond(200, "Ignored release action: ".$evt->action);
}

// the tag name ends up in a download URL and in a local path
$tagName = $evt->release->tag_name;
if (!is_string($tagName) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $tagName) || strpos($tagName, '..') !== false) {
    respond(400, "NOT SYNCED TO REGISTER. Unsupported tag name: use only letters, digits, '.', '_' and '-'.");
}

// for Technisch Register: match on URI of repo
$repoInfo = getRepoInfoByURL($reposArr, $evt->repository->html_url);
if ($repoInfo == false) {
    respond(200, "NOT SYNCED TO REGISTER. Repo information not found in repos.json or repos.json is not synced yet on Github. Fix repos.json or retry later.", "repository: ".$evt->repository->html_url);
}
if (strlen($repoInfo['id']) < 2) {
    respond(200, "NOT SYNCED TO REGISTER. Repo id is too short");
}

// Build the URL from repos.json, not from the payload, and download through refs/tags/:
// that only resolves tags of this repository itself, not commits or tags of forks.
// example:
// https://github.com/Geonovum/NEN3610/archive/refs/tags/v1.0.0.zip
$repoURL = rtrim($repoInfo['url'], '/');
if (strpos($repoURL, 'https://github.com/') !== 0) {
    respond(200, "NOT SYNCED TO REGISTER. The url in repos.json is not a https://github.com/ repository URL");
}
$zipballUrl = $repoURL.'/archive/refs/tags/'.$tagName.'.zip';
$tempZipName = $baseDir.'/'.$tmpDir.'/'.$repoInfo['id'].'.zip';
webhookLog("processing ".$repoInfo['id']." ".$tagName." for ".basename($envDir));
// Use a streaming writer to avoid the script to fail during processing of large files (like for TPOD)
$zipStream = @fopen($zipballUrl, 'r');
if ($zipStream === false) {
    respond(502, "NOT SYNCED TO REGISTER. Could not download ".$zipballUrl.": does the tag exist in this repository, and is the repository public?");
}
$written = file_put_contents($tempZipName, $zipStream);
fclose($zipStream);
if ($written === false) {
    respond(500, "NOT SYNCED TO REGISTER. The ZIP file could not be stored on the server.", "could not write ".$tempZipName);
}
echo "ZIP downloaded from GitHub: " .$zipballUrl ."\n";
$zip = new ZipArchive;
$res = $zip->open($tempZipName);
if ($res !== true) {
    @unlink($tempZipName);
    respond(500, "NOT SYNCED TO REGISTER. Something went wrong in processing the ZIP file", "ZipArchive::open error ".$res);
}

// Use a staging dir to get all the documents
$tmpZipDir = $baseDir.'/'.$tmpDir.'/'.$repoInfo['id'].'_'.$tagName;
@mkdir($tmpZipDir, 0775, true);
$skipped = extractArtefacts($zip, $tmpZipDir, $descriptions, $allowedExtensions);
$zip->close();
// 2019-09-18: first remove all existing directories of a standaard. Remove any old data of the filetypes / descriptions
foreach (array_keys($descriptions) as $fileType) {
    $fileTypeDir = $envDir.'/'.$fileType."/".$repoInfo['id'];
    if (file_exists($fileTypeDir)) {
        // rename instead of remove, to have a backup
        $backupDirFt = $backupDir.'/'.$fileType;
        $backupDirFtRepo = $backupDirFt.'/'.$repoInfo['id'];
        if (!file_exists($backupDirFt)) {
            mkdir($backupDirFt, 0775, true);
        }
        // cpdir_recursive($fileTypeDir, $backupDirFt);
        // rmdir_recursive($fileTypeDir);
        if (!rename($fileTypeDir, $backupDirFtRepo)) {
            webhookLog("backup of ".$fileType."/".$repoInfo['id']." failed (".$backupDirFtRepo." already exists?): the old files stay in place");
        }
    }
}
// zipfile is of format {repo}-{tagnumber-without-v}.zip.
// get the name of the extracted zip from the staging dir and copy the allowed files to the repoName:
$synced = array();
$results = scandir($tmpZipDir);
foreach ($results as $result) {
    if ($result === '.' or $result === '..') {
        continue;
    }
    if (is_dir($tmpZipDir. '/' . $result)) {
        // first dir is the repository dir. We need to copy the contents of that directory
        $contentDir = $tmpZipDir. '/' . $result;
        $subDirs = scandir($contentDir);
        foreach ($subDirs as $subDir) {
            if ($subDir === '.' or $subDir === '..') {
                continue;
            }
            if (isset($descriptions[$subDir])) {
                // the repo contains a subdirectory that is an artefact, as listed in the descriptions. Copy the dir then to the corresponding artefact directory
                $newBaseDir = $envDir.'/'.$subDir.'/'.$repoInfo['id'];
                echo "Resource directory: " .$subDir.'/'.$repoInfo['id']."\n";
                if (!file_exists($newBaseDir)) {
                    mkdir($newBaseDir, 0775, true);
                }
                // 2019-09-18: the new directory does not include a version directory, use the BaseDir directly
                echo "Sync ".$result.'/'.$subDir." directory to: ".$subDir.'/'.$repoInfo['id']."\n";
                cpdir_recursive($tmpZipDir.'/'.$result.'/'.$subDir, $newBaseDir);
                $synced[] = $subDir;
            }
        }
    }
}
if ($skipped) {
    echo "Skipped ".count($skipped)." file(s), these are not published:\n";
    foreach (array_slice($skipped, 0, 50) as $file) {
        echo "  ".$file."\n";
    }
    if (count($skipped) > 50) {
        echo "  ... and ".(count($skipped) - 50)." more\n";
    }
}
// Remove staging and tmp files
unlink($tempZipName);
rmdir_recursive($tmpZipDir);
webhookLog("200 published ".$repoInfo['id']." ".$tagName." to ".basename($envDir).": ".($synced ? implode(', ', $synced) : 'no artefact directories')
    .($skipped ? "; skipped ".count($skipped).": ".implode(', ', array_slice($skipped, 0, 50)) : ''));
