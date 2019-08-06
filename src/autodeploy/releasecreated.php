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

// security check first
$hubSignature = $_SERVER['HTTP_X_HUB_SIGNATURE'];
$messageBody = file_get_contents('php://input');
list($algo, $hash) = explode('=', $hubSignature, 2) + ['', ''];
if ($hash !== hash_hmac($algo, $messageBody, $hubSecret)) {
    header('HTTP/1.0 403 Forbidden');
    echo "Invalid Signature!";
    trigger_error("403 forbidden, secret is missing", E_USER_WARNING);
    return;
}

// Prevent accidental XSS
header('Content-type: text/plain');

// process the config of repositories to get all informationmodels that are allowed to publish artefacts
$reposArr = array();
$reposJson = file_get_contents($reposURL);
$reposArr = json_decode($reposJson, true);

function getRepoInfoByURL($reposArr, $repoUrl){
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
if ($_POST['payload']) {
    $evt = json_decode($_POST['payload']);
    // a release (published) is supposed to be copied to production , a prerelease to staging
    if ($evt->{'action'}=='published' || $evt->{'action'}=='prereleased') {
        // if the release has been published, continue creating the directories and copying files
        $envDir = $baseDir;
        if ($evt->{'action'}=='published') {
          $envDir = $baseDir.'/'.$productionDir;
        } else {
          $envDir = $baseDir.'/'.$stagingDir;
        }
        $tagName = $evt->release->tag_name;
        // TODO: check with Frank and original code versionnumber: number without a prefix v?
        $version = str_replace('v','',$tagName);
        // for TR: match on URI of repo
        $repoURL = strtoupper($evt->repository->html_url);
        $repoInfo = getRepoInfoByURL($reposArr, $repoURL);
        if ('id' in $repoInfo) {
          // the github zipball url does not seem to work properly, so fetch the zip based on the URL of the tagname
          // example:
          // https://github.com/thijsbrentjens/wfs-storedqueries/archive/def-hr-wpgs-20171223.zip
          $zipballUrl = $repoURL.'/archive/'.$tagName.'.zip';
          $tempZipName = $baseDir.'/'.$tmpDir.'/'.$repoInfo['id'].'.zip';
          file_put_contents($tempZipName, file_get_contents($zipballUrl));
          $zip = new ZipArchive;
          $res = $zip->open($tempZipName);
          if ($res === true) {
              // Use a staging dir to get all the documents
              $tmpZipDir = $baseDir.'/'.$tmpDir.'/'.$repoInfo['id'].'_'.$tagName;
              $zip->extractTo($tmpZipDir);
              $zip->close();
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
                            $newDir = $newBaseDir.'/'.$version;
                            mkdir($newDir, 0777, true);
                            cpdir_recursive($tmpZipDir.'/'.$result.'/'.$subDir, $newDir);
                        }
                      }
                  }
              }
              // Remove working files
              unlink($tempZipName);
              rmdir_recursive($tmpZipDir);
          } else {
              trigger_error("Something went wrong in processing the zip file", E_USER_WARNING);
          }
        }
    }
}
