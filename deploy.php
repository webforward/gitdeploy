<?php
/**
 * GitDeploy - PHP email creation and transport class.
 *
 * @see https://github.com/webforward/gitdeploy/ GitDeploy
 *
 * @author    WEBFWD Limited t/a Webforward
 * @author    Richard Leishman
 * @license   https://tldrlegal.com/license/mit-license MIT License
 */

require 'src/GitDeploy.php';

// If you are going to use this script, you must change the secret as you do not want anyone to be able to access
// it directly.
$secret = 'changeme';

if (!isset($_GET['secret']) || ($_GET['secret'] !== $secret || $secret === 'changeme')) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

//////
use Webforward\GitDeploy;
$gd = new GitDeploy();

// As a minimum, you need to set the following two options

/** @var string remote_repository
 * The repository url which you would like to deploy
 * By default this will use the branch `master` unless you specify.
 */
$gd->remote_repository = 'git@github.com:williammalone/Simple-HTML5-Drawing-App.git';

/** @var string target_dir
 * The directory in which you would like the repository deployed to, usually a live website root
 */
$gd->target_dir = '~/public_html';

///// Optional

// Git Related
$gd->branch = 'master';                        // The git branch which you would like to deploy, defaults to `master` when not set.
$gd->temp_dir = '/tmp/gitdeploy';              // Where temporary files will be stored, automatically generated inside /tmp if not set.
$gd->version_file = '~/public_html/VERSION';   // Store the deployment commit ID in a file.
$gd->delete_files = false;                     // Delete all files in target_dir that do not exist on git.
$gd->git_rm = true;                            // Remove files which are marked for removal on the git commit.
$gd->exclude_files = ['.git'];                 // Array of all folders or files which you do not want to be deployed from git.

// Backup
$gd->backup_dir = '/path/to/backup/dir/';      // Where you would like to store a timestamped tar.gz backup of target_dir before deployment starts, blank or false is disabled.

// Composer
$gd->use_composer = true;                      // Run `composer install` during the deployment.
$gd->composer_opts = '--no-dev';               // Additional composer options to be ran on `composer install`.
$gd->composer_home = '~/.composer';            // Change the composer home directory, false or blank leaves it as default.

// NPM
$gd->use_npm = true;                           // Run `npm install` during the deployment

// Performance and Cleanliness
$gd->clean_up = true;                          // Clean up and remove temp_dir when finished. False is quicker and uses more space, but true is better.
$gd->time_limit = 300;                         // Attempt to set PHP time_out, default is 300 if not set. Some shared hosting providers do not allow you to change their setting.

// Notification
$gd->email_recipients = ['info@domain.com'];   // default=[], String or Array of email recipients for success and failed emails.
$gd->email_on_success = false;                 // default=false, If to email email_recipients on successful deployment. Could end up being a lot of emails of you commit regularly.
$gd->email_on_error = true;                    // default=true, If to email email_recipients on errored deployment.

// Run the deployment
$gd->deploy();