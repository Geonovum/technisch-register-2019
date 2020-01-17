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

/*
2019-09-18: Thijs: disable the security check with a secret. this is not needed because we have an additional configuration check (in the corresponding Github repo)
*/

// Prevent accidental XSS
header('Content-type: text/plain');

// process the config of repositories to get all informationmodels that are allowed to publish artefacts
$reposArr = array();
$reposJson = file_get_contents($reposURL);
$reposArr = json_decode($reposJson, true);

function getRepoInfoByURL($reposArr, $repoUrl)
{
    // get the repo information for a model, using the repoURL
    $info;
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
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $evt = json_decode($messageBody);
    // a release (published or created) is supposed to be copied to production, a prerelease to staging
    if ($evt->{'action'}=='published' || $evt->{'action'}=='prereleased' || $evt->{'action'}=='created') {
        // if the release has been published, continue creating the directories and copying files
        $envDir = $baseDir;
        // TODO: prerelease false is a better check?
        if ($evt->{'action'}=='published' || $evt->{'action'}=='created') {
            $envDir = $baseDir.'/'.$productionDir;
        } else {
            $envDir = $baseDir.'/'.$stagingDir;
        }
        $tagName = $evt->release->tag_name;
        // TODO: redirect to release script
        // for Technisch Register: match on URI of repo
        $repoURL = strtoupper($evt->repository->html_url);
        $repoInfo = getRepoInfoByURL($reposArr, $repoURL);
        // the github zipball url does not seem to work properly, so fetch the zip based on the URL of the tagname
        // example:
        // https://github.com/thijsbrentjens/wfs-storedqueries/archive/def-hr-wpgs-20171223.zip
        $zipballUrl = $repoURL.'/archive/'.$tagName.'.zip';
        $tempZipName = $baseDir.'/'.$tmpDir.'/'.$repoInfo['id'].'.zip';
        // file_put_contents($tempZipName, file_get_contents($zipballUrl));
        // Use a streaming writer to avoid the script to fail during processing of large files (like for TPOD)
        file_put_contents($tempZipName, fopen($zipballUrl, 'r'));
        $zip = new ZipArchive;
        $res = $zip->open($tempZipName);
        if ($res === true) {
            // Use a staging dir to get all the documents
            $tmpZipDir = $baseDir.'/'.$tmpDir.'/'.$repoInfo['id'].'_'.$tagName;
            $zip->extractTo($tmpZipDir);
            $zip->close();
            // 2019-09-18: first remove all existing directories of a standaard. Remove any old data of the filetypes / descriptions
            foreach (array_keys($descriptions) as $fileType) {
                $fileTypeDir = $envDir.'/'.$fileType."/".$repoInfo['id'];
                if (file_exists($fileTypeDir)) {
                    rmdir_recursive($fileTypeDir);
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
                            if (!file_exists($newBaseDir)) {
                                mkdir($newBaseDir, 0777, true);
                            }
                            // 2019-09-18: the new directory does not include a version directory, use the BaseDir directly
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
        }
    }
}
