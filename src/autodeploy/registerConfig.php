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
include 'githubConfig.php';

// Settings that differ per installation can be set in the environment (REGISTER_*), for example
// in a container. Without them, the values of the current server below are used.
$registerEnv = function ($name, $default) {
    $value = getenv($name);
    return ($value === false || $value === '') ? $default : $value;
};

// base path on server
$baseDir = $registerEnv('REGISTER_BASE_DIR', '/var/www/geostandaarden/v2');

$backupDir = $baseDir.'/backup';

// base url of register, needed for creation of some links
// for testing use this URL:
// $baseURL = 'http://35.164.200.141/v2/production/';
$baseURL = $registerEnv('REGISTER_BASE_URL', 'https://register.geostandaarden.nl/');

// the subdiroctory in the $baseDir where the documents for production should be published
$productionDir = 'production';

// NOTE: staging mechanism is not implemented yet
$stagingDir = 'staging';

// a writable subdirectory in the baseDir, to unpack ZIP-files from Github
$tmpDir = 'tmp';

// file containing the shared secret of the GitHub webhooks (only the secret, on one line).
// Keep it outside the DocumentRoot and out of git, readable for the webserver only.
// The environment variable REGISTER_WEBHOOK_SECRET, when set, is used instead of this file.
// The webhook rejects all requests when neither holds a secret.
$webhookSecretFile = $registerEnv('REGISTER_WEBHOOK_SECRET_FILE', $baseDir.'/webhook-secret');

// log of all webhook requests and their outcome, outside the DocumentRoot and writable for
// the webserver. When it cannot be written, lines go to the PHP/Apache error log instead.
$webhookLogFile = $baseDir.'/log/webhook.log';

// File types (lowercase extension without the dot) that are published from a release.
// Other files are skipped and listed in the webhook response. Server-side executable
// types (php, phtml, phar, asp, cgi, ...) are always skipped, also when listed here.
$allowedExtensions = array(
    // models, schemas and rules
    'xmi', 'eap', 'eapx', 'qea', 'xsd', 'xml', 'gml', 'wsdl', 'json', 'jsonld', 'yaml', 'yml',
    'sch', 'xsl', 'xslt', 'cva', 'gc', 'rdf', 'ttl', 'owl',
    // tables and documents
    'csv', 'xls', 'xlsx', 'ods', 'pdf', 'doc', 'docx', 'txt', 'md', 'vsd',
    // documentation pages (IMGeo publishes HTML documentation with CSS and JavaScript)
    'html', 'htm', 'css', 'js',
    // visualisation and symbols
    'sld', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'eps', 'ttf', 'otf',
    // archives
    'zip',
    // files without an extension (e.g. xlink/1.0.0/web-location in IMKAD)
    '',
);

// The result of the last release of each standard, for its maintainers:
// <statusDir>/<id>.txt, published as <statusURL><id>.txt. Writable for the webserver.
$statusDir = $baseDir.'/'.$productionDir.'/autodeploy/status';
$statusURL = $baseURL.'autodeploy/status/';

// marker files of successfully processed GitHub deliveries, so a delivery is processed once
$deliveriesDir = $baseDir.'/log/deliveries';

// number of backups kept per artefact type and standard (backup/<type>/<id>.<timestamp>)
$backupsToKeep = (int) $registerEnv('REGISTER_BACKUPS_TO_KEEP', 5);
?>
