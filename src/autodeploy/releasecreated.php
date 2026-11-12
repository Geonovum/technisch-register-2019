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

// Send the response to GitHub now and keep processing after GitHub has it: GitHub stops waiting
// after 10 seconds, and downloading and copying a large release takes longer.
function acceptAndContinue($message)
{
    global $accepted;
    ignore_user_abort(true);
    set_time_limit(0);
    // compression would hold the response back until the script ends
    if (function_exists('apache_setenv')) {
        apache_setenv('no-gzip', '1');
    }
    ini_set('zlib.output_compression', '0');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(202);
    header('Connection: close');
    header('Content-Length: '.strlen($message));
    echo $message;
    flush();
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    $accepted = true;
    webhookLog('202 '.$message);
}

// Write the result of the release for the maintainers of the standard, at <statusURL><id>.txt.
// This file is public: it must not contain paths or other details of the server.
function writeStatus($state, $lines)
{
    global $statusFile, $deliveryId, $repoInfo, $tagName, $envName;
    $text = "Technisch register: result of the last release\n\n"
        ."Standard:    ".$repoInfo['id']."\n"
        ."Release tag: ".$tagName."\n"
        ."Environment: ".$envName."\n"
        ."Delivery:    ".$deliveryId."\n"
        ."Updated:     ".date('c')."\n"
        ."Status:      ".$state."\n"
        .($lines ? "\n".implode("\n", $lines)."\n" : '');
    if (@file_put_contents($statusFile.'.tmp', $text) === false || !@rename($statusFile.'.tmp', $statusFile)) {
        webhookLog("could not write status file ".$statusFile);
    }
}

// End the processing after the response was sent: write the status file and the log, and stop.
function finish($state, $lines, $logDetail = '')
{
    global $repoInfo, $tagName, $envName, $finished;
    $finished = true;
    writeStatus($state, $lines);
    webhookLog($state.' '.$repoInfo['id'].' '.$tagName.' to '.$envName.($logDetail === '' ? '' : ': '.$logDetail));
    exit;
}

// Nobody sees the output after the response was sent: record it when the script stops unexpectedly.
$accepted = false;
$finished = false;
register_shutdown_function(function () {
    global $accepted, $finished;
    if ($accepted && !$finished) {
        $error = error_get_last();
        finish('failed', array("Processing stopped unexpectedly. Contact the maintainer of the register."),
            $error ? $error['message'].' in '.$error['file'].':'.$error['line'] : 'unknown cause');
    }
});

// Keep the newest $keep backups (<id>.<timestamp>) of a standard in a backup directory.
function pruneBackups($backupDirFt, $id, $keep)
{
    $backups = array();
    foreach (scandir($backupDirFt) as $entry) {
        if (preg_match('/\A'.preg_quote($id, '/').'\.\d{8}-\d{6}-\d{6}\z/', $entry)) {
            $backups[] = $entry;
        }
    }
    sort($backups);
    foreach (array_slice($backups, 0, max(0, count($backups) - $keep)) as $old) {
        rmdir_recursive($backupDirFt.'/'.$old);
    }
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
$envName = basename($envDir);
$statusFile = $statusDir.'/'.preg_replace('/[^A-Za-z0-9_-]/', '_', $repoInfo['id']).'.txt';
$statusLink = $statusURL.preg_replace('/[^A-Za-z0-9_-]/', '_', $repoInfo['id']).'.txt';

// GitHub sends the same delivery id again on Redeliver. A delivery that was published
// without errors is not processed again; after a failure it can be redelivered.
$deliveryMarker = $deliveriesDir.'/'.$deliveryId;
if ($deliveryId !== '-' && file_exists($deliveryMarker)) {
    respond(200, "Already processed: this delivery was published before, nothing to do. Result: ".$statusLink);
}

// one release of a standard at a time
$lockHandle = @fopen($baseDir.'/'.$tmpDir.'/'.$repoInfo['id'].'.lock', 'c');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    respond(409, "NOT SYNCED TO REGISTER. A release of ".$repoInfo['id']." is being processed right now. Retry later with Redeliver.");
}

acceptAndContinue("Accepted: publishing ".$repoInfo['id']." ".$tagName." to ".$envName." in the background. Result: ".$statusLink);
writeStatus('processing', array());
$lines = array();

$tempZipName = $baseDir.'/'.$tmpDir.'/'.$repoInfo['id'].'.zip';
// Use a streaming writer to avoid the script to fail during processing of large files (like for TPOD)
$zipStream = @fopen($zipballUrl, 'r');
if ($zipStream === false) {
    finish('failed', array("Could not download ".$zipballUrl.": does the tag exist in this repository, and is the repository public?"));
}
$written = file_put_contents($tempZipName, $zipStream);
fclose($zipStream);
if ($written === false) {
    finish('failed', array("The ZIP file could not be stored on the server. Contact the maintainer of the register."), "could not write ".$tempZipName);
}
$lines[] = "ZIP downloaded from GitHub: ".$zipballUrl;
$zip = new ZipArchive;
$res = $zip->open($tempZipName);
if ($res !== true) {
    @unlink($tempZipName);
    finish('failed', array("Something went wrong in processing the ZIP file. Contact the maintainer of the register."), "ZipArchive::open error ".$res);
}

// Use a staging dir to get all the documents
$tmpZipDir = $baseDir.'/'.$tmpDir.'/'.$repoInfo['id'].'_'.$tagName;
@mkdir($tmpZipDir, 0775, true);
$skipped = extractArtefacts($zip, $tmpZipDir, $descriptions, $allowedExtensions);
$zip->close();

// A release replaces all published directories of the standard. Move the current ones to
// backup/<type>/<id>.<timestamp> and keep the newest $backupsToKeep of them.
$date = new DateTime();
$backupTimeStamp = $date->format('Ymd-His-u');
$backupProblems = array();
foreach (array_keys($descriptions) as $fileType) {
    $fileTypeDir = $envDir.'/'.$fileType."/".$repoInfo['id'];
    if (file_exists($fileTypeDir)) {
        $backupDirFt = $backupDir.'/'.$fileType;
        if (!file_exists($backupDirFt)) {
            @mkdir($backupDirFt, 0775, true);
        }
        if (@rename($fileTypeDir, $backupDirFt.'/'.$repoInfo['id'].'.'.$backupTimeStamp)) {
            pruneBackups($backupDirFt, $repoInfo['id'], $backupsToKeep);
        } else {
            $backupProblems[] = $fileType.'/'.$repoInfo['id'];
        }
    }
}

// zipfile is of format {repo}-{tagnumber-without-v}.zip.
// get the name of the extracted zip from the staging dir and copy the allowed files to the repoName:
$synced = array();
$failures = array();
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
                $lines[] = "Resource directory: " .$subDir.'/'.$repoInfo['id'];
                if (!file_exists($newBaseDir)) {
                    @mkdir($newBaseDir, 0775, true);
                }
                // 2019-09-18: the new directory does not include a version directory, use the BaseDir directly
                $lines[] = "Sync ".$result.'/'.$subDir." directory to: ".$subDir.'/'.$repoInfo['id'];
                cpdir_recursive($tmpZipDir.'/'.$result.'/'.$subDir, $newBaseDir, $failures);
                $synced[] = $subDir;
            }
        }
    }
}
if (!$synced) {
    $lines[] = "The release contains no artefact directories.";
}
if ($skipped) {
    $lines[] = "Skipped ".count($skipped)." file(s), these are not published:";
    foreach (array_slice($skipped, 0, 200) as $file) {
        $lines[] = "  ".$file;
    }
    if (count($skipped) > 200) {
        $lines[] = "  ... and ".(count($skipped) - 200)." more";
    }
}
// paths relative to the environment, the status file must not show server paths
$failedFiles = array();
foreach ($failures as $path) {
    $failedFiles[] = strpos($path, $envDir.'/') === 0 ? substr($path, strlen($envDir) + 1) : basename($path);
}
if ($failedFiles) {
    $lines[] = "Could not write ".count($failedFiles)." file(s) on the server, these may be outdated. Contact the maintainer of the register:";
    foreach (array_slice($failedFiles, 0, 200) as $file) {
        $lines[] = "  ".$file;
    }
    if (count($failedFiles) > 200) {
        $lines[] = "  ... and ".(count($failedFiles) - 200)." more";
    }
}
if ($backupProblems) {
    $lines[] = "The previous version of ".implode(', ', $backupProblems)." could not be moved to the backup: files removed from the repository may still be online. Contact the maintainer of the register.";
}

// Remove staging and tmp files
@unlink($tempZipName);
rmdir_recursive($tmpZipDir);

$logDetail = ($synced ? implode(', ', $synced) : 'no artefact directories')
    .($skipped ? "; skipped ".count($skipped).": ".implode(', ', array_slice($skipped, 0, 50)) : '')
    .($failures ? "; could not write ".count($failures).": ".implode(', ', array_slice($failures, 0, 50)) : '')
    .($backupProblems ? "; backup failed: ".implode(', ', $backupProblems) : '');
if ($failures || $backupProblems) {
    finish('published with errors', $lines, $logDetail);
}

// remember this delivery, and forget deliveries older than 90 days
if ($deliveryId !== '-') {
    @mkdir($deliveriesDir, 0775, true);
    @touch($deliveryMarker);
    $markers = @scandir($deliveriesDir);
    foreach ($markers ? $markers : array() as $marker) {
        if ($marker[0] !== '.' && @filemtime($deliveriesDir.'/'.$marker) < time() - 90 * 86400) {
            @unlink($deliveriesDir.'/'.$marker);
        }
    }
}
finish('published', $lines, $logDetail);
