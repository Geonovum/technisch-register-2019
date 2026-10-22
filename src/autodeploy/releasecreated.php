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

// The JSON payload as sent by Github
$messageBody = file_get_contents('php://input');

// Prevent accidental XSS
header('Content-type: text/plain');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Only POST requests from the GitHub webhook are accepted";
    exit;
}

// Only accept requests signed with the shared webhook secret (header X-Hub-Signature-256).
// The repository URL in the payload is chosen by the sender, so repos.json alone does not
// authenticate a request. Without a configured secret nothing is processed.
$webhookSecret = is_readable($webhookSecretFile) ? trim(file_get_contents($webhookSecretFile)) : '';
if ($webhookSecret === '') {
    http_response_code(500);
    echo "NOT SYNCED TO REGISTER. The webhook secret is not configured on the server.";
    exit;
}
$signature = isset($_SERVER['HTTP_X_HUB_SIGNATURE_256']) ? $_SERVER['HTTP_X_HUB_SIGNATURE_256'] : '';
if (!hash_equals('sha256='.hash_hmac('sha256', $messageBody, $webhookSecret), $signature)) {
    http_response_code(401);
    echo "NOT SYNCED TO REGISTER. Invalid or missing signature: check the secret of the webhook.";
    exit;
}

$event = isset($_SERVER['HTTP_X_GITHUB_EVENT']) ? $_SERVER['HTTP_X_GITHUB_EVENT'] : '';
if ($event === 'ping') {
    echo "pong: the webhook and its secret are configured correctly";
    exit;
}
if ($event !== 'release') {
    echo "Ignored event: ".$event;
    exit;
}

// process the config of repositories to get all informationmodels that are allowed to publish artefacts
$reposArr = array();
$reposJson = file_get_contents($reposURL);
$reposArr = json_decode($reposJson, true);

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
$descJson = file_get_contents($descriptionsURL);
$descriptions = json_decode($descJson, true);

// process the GitHub release information
// JSON format is used
$evt = json_decode($messageBody);
if (!is_object($evt) || !isset($evt->action, $evt->release, $evt->repository)) {
    http_response_code(400);
    echo "NOT SYNCED TO REGISTER. The payload is not a release event in JSON: set the content type of the webhook to application/json.";
    exit;
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
    echo "Ignored release action: ".$evt->action;
    exit;
}

// the tag name ends up in a download URL and in a local path
$tagName = $evt->release->tag_name;
if (!is_string($tagName) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $tagName) || strpos($tagName, '..') !== false) {
    http_response_code(400);
    echo "NOT SYNCED TO REGISTER. Unsupported tag name: use only letters, digits, '.', '_' and '-'.";
    exit;
}

// for Technisch Register: match on URI of repo
$repoInfo = getRepoInfoByURL($reposArr, $evt->repository->html_url);
if ($repoInfo == false) {
    echo "NOT SYNCED TO REGISTER. Repo information not found in repos.json or repos.json is not synced yet on Github. Fix repos.json or retry later.";
    exit;
}
if (strlen($repoInfo['id']) < 2) {
    echo "NOT SYNCED TO REGISTER. Repo id is too short";
    exit;
}

// Build the URL from repos.json, not from the payload, and download through refs/tags/:
// that only resolves tags of this repository itself, not commits or tags of forks.
// example:
// https://github.com/Geonovum/NEN3610/archive/refs/tags/v1.0.0.zip
$repoURL = rtrim($repoInfo['url'], '/');
if (strpos($repoURL, 'https://github.com/') !== 0) {
    echo "NOT SYNCED TO REGISTER. The url in repos.json is not a https://github.com/ repository URL";
    exit;
}
$zipballUrl = $repoURL.'/archive/refs/tags/'.$tagName.'.zip';
$tempZipName = $baseDir.'/'.$tmpDir.'/'.$repoInfo['id'].'.zip';
// Use a streaming writer to avoid the script to fail during processing of large files (like for TPOD)
$zipStream = @fopen($zipballUrl, 'r');
if ($zipStream === false) {
    http_response_code(502);
    echo "NOT SYNCED TO REGISTER. Could not download ".$zipballUrl.": does the tag exist in this repository, and is the repository public?";
    exit;
}
file_put_contents($tempZipName, $zipStream);
fclose($zipStream);
echo "ZIP downloaded from GitHub: " .$zipballUrl ."\n";
$zip = new ZipArchive;
$res = $zip->open($tempZipName);
$date = new DateTime();
$backupTimeStamp = $date->getTimestamp();

if ($res === true) {
    // Use a staging dir to get all the documents
    $tmpZipDir = $baseDir.'/'.$tmpDir.'/'.$repoInfo['id'].'_'.$tagName;
    $zip->extractTo($tmpZipDir);
    $zip->close();
    // 2019-09-18: first remove all existing directories of a standaard. Remove any old data of the filetypes / descriptions
    foreach (array_keys($descriptions) as $fileType) {
        $fileTypeDir = $envDir.'/'.$fileType."/".$repoInfo['id'];
        if (file_exists($fileTypeDir)) {
            // rename instead of remove, to have a backup
            $backupDirFt = $backupDir.'/'.$fileType;
            $backupDirFtRepo = $backupDirFt.'/'.$repoInfo['id'];
            if (!file_exists($backupDirFt)) {
                mkdir($backupDirFt, 0777, true);
            }
            // cpdir_recursive($fileTypeDir, $backupDirFt);
            // rmdir_recursive($fileTypeDir);
            rename($fileTypeDir, $backupDirFtRepo);
        }
    }
    // zipfile is of format {repo}-{tagnumber-without-v}.zip.
    // get the name of the extracted zip from the staging dir and copy the allowed files to the repoName:
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
                if ($descriptions[$subDir]) {
                    // the repo contains a subdirectory that is an artefact, as listed in the descriptions. Copy the dir then to the corresponding artefact directory
                    $newBaseDir = $envDir.'/'.$subDir.'/'.$repoInfo['id'];
                    echo "Resource directory: " .$subDir.'/'.$repoInfo['id']."\n";
                    if (!file_exists($newBaseDir)) {
                        mkdir($newBaseDir, 0777, true);
                    }
                    // 2019-09-18: the new directory does not include a version directory, use the BaseDir directly
                    echo "Sync ".$result.'/'.$subDir." directory to: ".$subDir.'/'.$repoInfo['id']."\n";
                    cpdir_recursive($tmpZipDir.'/'.$result.'/'.$subDir, $newBaseDir);
                }
            }
        }
    }
    // Remove staging and tmp files
    unlink($tempZipName);
    rmdir_recursive($tmpZipDir);
} else {
    trigger_error("Something went wrong in processing the zip file", E_USER_WARNING);
    echo "Something went wrong in processing the ZIP file";
}
