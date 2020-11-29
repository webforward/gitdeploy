<?php
require 'deploy.php';

// You must change the secret as you do not want anyone to be able to access this script directly.
$secret = 'changeme';

if (!isset($_GET['secret']) || ($_GET['secret'] !== $secret || $secret === 'changeme')) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

//////
use Webforward\GitDeploy;

$gitDeploy = new GitDeploy();

$gitDeploy->remote_repository = 'git@github.com:williammalone/Simple-HTML5-Drawing-App.git';
$gitDeploy->branch = 'master';
$gitDeploy->target_dir = '~/public_html';

$gitDeploy->deploy();