<?php
/*
MIT License

Copyright (c) 2020 WEBFWD LTD t/a Webforward

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

namespace Webforward;

class GitDeploy
{
    public $remote_repository = 'git@provider.org:owner/repository.git';
    public $branch = 'master';
    public $target_dir = '';
    public $version_file = '';
    public $git_rm = true;

    public $delete_files = false;
    public $exclude_files = ['.git', 'LICENSE*', 'README*'];
    public $temp_dir = '';

    public $clean_up = true;

    public $time_limit = 300; // 5 Minutes - false to not set

    public $backup = false;
    public $backup_dir = '';

    public $use_composer = false;
    public $composer_opts = '--no-dev';
    public $composer_home = '';

    public $use_npm = false;

    public $email_on_success = false;
    public $email_on_error = true;
    public $email_recipients = [];

    private $log = '';
    const NL = "\r\n";

    function __construct() {

    }

    public function checkVariables(): void {

        // Repository
        if (!preg_match('/^((git@|http(s)?:\/\/)([\w.@]+)([\/:]))([\w,\-_]+)\/([\w,\-_]+)(.git)?((\/)?)$/', $this->remote_repository))
            $this->error('Remote Repository is not a valid SSH git repository.');

        // Branches
        if (!is_string($this->branch)) $this->error('Branch must be a value branch.');
        if (empty($this->branch)) $this->branch = 'master';

        // Target Dir
        $this->target_dir = rtrim($this->target_dir, '/\\');
        if (empty($this->target_dir)) $this->error('Target Directory must have a value.');
        if (!is_dir($this->target_dir)) $this->error('Target Directory does not exist.');
        if (!is_writable($this->target_dir)) $this->error('Target Directory is not writable.');

        // Git rm
        if (!is_bool($this->git_rm)) $this->error('Git rm must be true or false.');

        // Delete Files
        if (!is_bool($this->delete_files)) $this->error('Delete Files must be true or false.');

        // Exclude Files
        if (!is_array($this->exclude_files)) $this->error('Exclude Files must be an array.');

        // Temp Dir
        if (empty($this->temp_dir)) $this->temp_dir = '/tmp/gd-' . md5($this->remote_repository);
        $this->temp_dir = rtrim($this->temp_dir, '/\\');
        if (!is_dir($this->temp_dir))
            if (!mkdir($this->temp_dir)) $this->error('Temporary Dir parent directory is not writable.');
            else rmdir($this->temp_dir);

        // Clean Up
        if (!is_bool($this->clean_up)) $this->error('Clean Up must be true or false.');

        // Version File
        if ($this->version_file !== false) {
            if (empty($this->version_file) || $this->version_file === true) $this->version_file = $this->target_dir . '/VERSION';
            elseif (!is_writable(dirname($this->version_file)))
                $this->error('Version file parent directory is not writable.');
        }

        // Time Limit
        if (!is_int($this->time_limit) && $this->time_limit !== false) $this->error('Time Limit must be false or an integer in seconds.');

        // Backup Dir
        if ($this->backup_dir === true) {
            $this->backup_dir = rtrim($this->backup_dir, '/\\');
            if (!is_writable($this->backup_dir))
                $this->error('Backup directory is not writable.');
        }

        // Composer
        if ($this->use_composer !== false) {
            if (!is_string($this->composer_opts)) $this->error('Composer Options must be a string.');
            if ($this->composer_home !== '') {
                $this->composer_home = rtrim($this->composer_home, '/\\');
                if (!is_dir($this->composer_home)) $this->error('Composer home directory does not exist.');
                if (!is_writable($this->composer_home)) $this->error('Composer home directory is not writable.');
            }
        }

        // Sending of emails
        if ($this->email_on_error !== false || $this->email_on_success !== false) {
            if (empty($this->email_recipients))
                $this->error('Email Recipients can not be empty if email reporting is turned on.');
        }

    }

    public function checkEnvironment(): void {
        $this->log('Checking the environment ...' . self::NL);
        $this->log('Running as <strong class="output">' . trim(shell_exec('whoami')) . '</strong>.' . self::NL);

        // Check if the functions we need are disabled for security reasons
        $required_functions = [
            'shell_exec',
            'exec'
        ];
        $disabled_functions = explode(',',
            ini_get('disable_functions'));
        if (!empty($disabled_functions)) foreach ($required_functions as $function) {
            if (in_array($function,
                $disabled_functions)) $this->error('<div class="error">PHP function<strong>' . $function . '</strong> is disabled. ' . 'It needs to be enabled on the server for this script to work.</div>');
        }


        // We need to check that we have the required applications installed on the server to perform this deploy
        $required_binaries = array(
            'git',
            'rsync'
        );
        if (in_array($this->backup_dir,
                [
                    false,
                    ''
                ]) === false) $required_binaries[] = 'tar';
        if ($this->use_composer !== false) $required_binaries[] = 'composer --no-ansi';

        foreach ($required_binaries as $binary) {
            $path = trim(shell_exec('which ' . $binary));
            if ($path !== '') {
                $version = strtok(shell_exec($binary . ' --version'),
                    "\n");
                $this->log('<strong>' . $binary . '</strong> (' . $version . ') Installed : ' . $path);
            } else {
                $this->error('<div class="error"><strong>' . $binary . '</strong> not available. ' . 'It needs to be installed on the server for this script to work.</div>');
            }
        }

        $this->log(self::NL . 'Environment <strong class="success">OK</strong>.' . self::NL);

    }

    public function deploy(): void {
        ob_implicit_flush(true);
        ob_end_flush();

        $this->header();

        $this->log([
            '<h1>GitDeply',
            '========</h1>'
        ]);

        $this->checkVariables();
        $this->checkEnvironment();

        $this->log([
            sprintf('Deploying  %s %s',
                $this->remote_repository,
                $this->branch),
            sprintf('To         %s' . self::NL,
                $this->target_dir)
        ]);

        // Clone the git branch to a temporary directory
        if (is_dir($this->temp_dir) !== true) {
            // Starting a fresh clone as the temporary directory does not already exist
            $this->command(sprintf('git clone --depth=1 --branch %s %s %s',
                $this->branch,
                $this->remote_repository,
                $this->temp_dir));
        } else {
            // Updating the existing temporary directory with the latest from the git repository
            $this->command(sprintf('git --git-dir="%s/.git" --work-tree="%s" fetch origin %s',
                $this->temp_dir,
                $this->temp_dir,
                $this->branch));
            $this->command(sprintf('git --git-dir="%s/.git" --work-tree="%s" reset --hard FETCH_HEAD',
                $this->temp_dir,
                $this->temp_dir));
        }

        chdir($this->temp_dir);

        // Update the submodules
        $this->command('git submodule update --init --recursive');

        // Install required composer packages
        if ($this->use_composer !== false) {
            $composer_file = $this->temp_dir . '/composer.json';
            if (is_file($composer_file)) {
                if ($this->composer_home !== '') putenv('COMPOSER_HOME=' . $this->composer_home);
                $this->command(sprintf('composer --no-ansi --no-interaction --no-progress --working-dir=%s install %s',
                    $this->temp_dir,
                    $this->composer_opts));
            } else {
                $this->warning(sprintf('We can not run `composer install` as %s does not exist',
                    $composer_file));
            }
        }

        // Install required composer packages
        if ($this->use_npm !== false) {
            $npm_file = $this->temp_dir . '/package.json';
            if (is_file($npm_file)) {
                $this->command(sprintf('npm install --no-color --prefix %s',
                    $this->temp_dir));
            } else {
                $this->warning(sprintf('We can not run `npm install` as %s does not exist',
                    $npm_file));
            }
        }

        // Create a backup of the target directory before we go further
        if ($this->backup !== false) {
            $backup_file = $this->backup_dir . '/' . implode('-', [basename($this->target_dir), md5($this->target_dir), date('YmdHis')]) . '.tar.gz';
            $this->command(sprintf('tar --exclude=\'%s*\' -czf %s %s',
                $this->temp_dir,
                $backup_file,
                $this->target_dir));
            $this->log(sprintf(
                'Backup of %s has been created at %s (%s)',
                $this->target_dir,
                $backup_file,
                $this->formatSize(filesize($backup_file))
            ));
        }

        // It is time to move things to live
        $exclude = '';
        foreach ($this->exclude_files as $ex) {
            $exclude .= ' --exclude=' . $ex;
        }

        $this->command(sprintf('rsync -rltgoDzvO %s/ %s/ %s %s',
            $this->temp_dir,
            $this->target_dir,
            ($this->delete_files === true) ? '--delete-after' : '',
            $exclude));

        // Delete files that were removed in the git commit and then remove parent direct if it is empty
        if ($this->git_rm === true) {
            $delete_files = $this->command('git log --diff-filter=D --summary | grep "^ delete mode" | cut -d " " -f5');
            if (!empty($delete_files)) {
                foreach ($delete_files as $file) {
                    $file_path = $this->target_dir . '/' . $file;
                    if (!file_exists($this->temp_dir . ' / ' . $file)) {
                        $this->log('Removing file ' . $file_path);
                        @unlink($file);
                        @rmdir(dirname($file));
                    }
                }
                $this->log(self::NL);
            }
        }

        // Create a file containing the commit of this deploy
        if ($this->version_file !== '') $this->command(sprintf('git --git-dir="%s/.git" --work-tree="%s" describe --always > %s',
            $this->temp_dir,
            $this->temp_dir,
            $this->version_file));

        $this->cleanUp();

        $this->log('<div class="success">Deploy Complete.</div>');

        $this->footer();
        $this->sendEmail();
    }

    private function cleanUp(): void {
        if ($this->clean_up === true && is_dir($this->temp_dir)) {
            $this->log('Cleaning up temporary files ...' . self::NL);
            $this->command(sprintf('rm -rf %s',
                $this->temp_dir));
        }
    }

    private function command($command): array {
        if (is_int($this->time_limit)) set_time_limit($this->time_limit);
        exec($command . ' 2>&1',
            $output_arr,
            $return_code);
        if ($return_code === 0) {
            $output = implode(self::NL, $output_arr);
            $output .= ob_get_contents();
            $this->log([
                sprintf('<span class="prompt">$</span> <span class="command">%s</span>',
                    trim(htmlentities($command ?: ''))),
                sprintf('<div class="output">%s</div>',
                    trim(htmlentities($output ?: '')))
            ]);
            return $output_arr;
        } else {
            $this->error([
                sprintf('Could not run command: <strong>%s</strong>',
                    htmlentities($command ?: '')),
                'Stopping the script to prevent any possible data loss.',
                'CHECK THE DATA IN YOUR TARGET DIR!'
            ]);
        }
        return [];
    }

    private function log($html = ''): void {
        if (is_array($html)) $html = implode(self::NL,
            $html);
        $html .= self::NL;
        $this->log .= $html;
        echo $html;
    }

    private function error($message): void {
        if (is_array($message)) $message = implode(self::NL,
            $message);
        $html = '<div class="error">Error: ' . $message . '</div>';
        $this->log($html);
        $this->cleanUp();
        $this->sendEmail(true);
        exit;
    }

    private function warning($message): void {
        $html = '<div class="warning">Warning: ' . $message . '</div>';
        $this->log($html);
    }

    private function sendEmail($error = false): void {
        if ($error === true && $this->email_on_error !== true) return;
        if ($error === false && $this->email_on_success !== true) return;
        if (empty($this->email_recipients) !== true) {
            if (is_string($this->email_recipients)) $this->email_recipients = [$this->email_recipients];
            $server_host = isset($_SERVER['HTTP_HOST']) ?? '';
            $subject = sprintf('GitDeploy %s on %s',
                ($error === true ? 'ERROR' : 'SUCCESS'),
                $server_host);
            $headers = 'MIME-Version: 1.0' . self::NL . 'Content-type:text/html;charset=UTF-8' . self::NL;
            if ($error === true) $headers .= 'X-Priority: 1 (Highest)' . self::NL . 'X-MSMail-Priority: High' . self::NL . 'Importance: High' . self::NL;
            foreach ($this->email_recipients as $recipient) {
                if (filter_var($recipient,
                        FILTER_VALIDATE_EMAIL) !== true) mail($recipient,
                    $subject,
                    $this->log,
                    $headers);
            }
        }
    }

    private function header(): void {
        $this->log('<!DOCTYPE html>
        <html lang="en">
        <head><meta charset="utf-8"><meta name="robots" content="noindex"><title>GitDeploy</title>
        <style>
        body { padding: 0 1em; background: #222; color: #fff; }
        h1 { font-size: 1.6em; }
        h1, .error { color: #c33; }
        .success { color: #1c9d1c; }
        .warning { color: #cc9433; }
        .prompt { color: #6c2dd4; }
        .command { color: #729fcf; }
        .output { color: #999; }
        </style></head><body><pre>');
    }

    private function footer(): void {
        $this->log('</pre></body></html>');
    }

    private function formatSize(int $bytes): string {
        if ($bytes >= 1073741824)
            return number_format($bytes / 1073741824, 2) . ' GB';
        elseif ($bytes >= 1048576)
            return number_format($bytes / 1048576, 2) . ' MB';
        elseif ($bytes >= 1024)
            return number_format($bytes / 1024, 2) . ' KB';
        elseif ($bytes > 1)
            return $bytes . ' bytes';
        elseif ($bytes == 1)
            return $bytes . ' byte';
        else
            return '0 bytes';
    }

}
