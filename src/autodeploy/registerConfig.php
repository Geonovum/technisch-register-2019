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

// base path on server
$baseDir = '/var/www/geostandaarden/v2';

// base url of register, needed for creation of some links
// for testing use this URL:
// $baseURL = 'http://35.164.200.141/v2/production/';
$baseURL = 'https://register.geostandaarden.nl/';

// the subdiroctory in the $baseDir where the documents for production should be published
$productionDir = 'production';

// NOTE: staging mechanism is not implemented yet
$stagingDir = 'staging';

// a writable subdirectory in the baseDir, to unpack ZIP-files from Github
$tmpDir = 'tmp';
?>
