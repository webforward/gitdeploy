# GitDeploy
_Easy to use automatic git deployment for PHP projects with composer and npm support._

## Requirements

* PHP 7.0 or above with `proc_open` and `shell_exec` functions enabled.
* `git` and `rsync` are required on the server that's running the script
  (_server machine_).
  - Optionally, `tar` is required for backup functionality (`backup_dir` option).
  - Optionally, `composer` is required for composer functionality (`use_composer`
  option).
  - Optionally, `npm` is required for composer functionality (`use_npm`
    option).
* The system user running PHP (e.g. `www-data`) needs to have the necessary
  access permissions for the `temp_dir` and `target_dir` locations on
  the _server machine_.
* If the Git repository you wish to deploy is private, the system user running PHP
  also needs to have the right SSH keys installed to access the remote repository. 
  See GitHub or BitBucket for examples.

## Usage - In your own project though composer

 1. Start by running the command `composer require webforward/gitdeploy` .
 2. Use the following code in your project and configure options:
 ```php
 use Webforward\GitDeploy; // This line must be at the top of a script, not inside a function
 $gd = new GitDeploy();
 $gd->remote_repository = 'https://github.com/williammalone/Simple-HTML5-Drawing-App.git';
 $gd->target_dir = '/home/user/public_html';
 // Your configurable options here, see deploy.php
 $gd->deploy();
 ```
 3. Setup a check/method in which the above code is not publicly accessible from 
 the internet, it should be placed behind an admin php session, or the requirement 
 to enter a secret key or password to run the deploy() function. Check `deploy.php` 
 for an easy method of how this can be implemented.
 4. Test the deployment by visiting the script url in your browser.
 5. Once happy, follow the steps of setting up a hook. See GitHub, BitBucket or 
 Generic Git for examples.
    
## Usage - Standalone

 1. Save a copy of `src/GitDeploy.php` within your website project.
 2. See `deploy.php` for an example or use the following code in your project 
 and configure options:
 ```php
 require 'path/to/src/GitDeploy.php';
 use Webforward\GitDeploy; // This line must be at the top of a script, not inside a function
 $gd = new GitDeploy();
 $gd->remote_repository = 'https://github.com/williammalone/Simple-HTML5-Drawing-App.git';
 $gd->target_dir = '/home/user/public_html';
 // Your configurable options here, see deploy.php
 $gd->deploy();
 ```
 3. Setup a check/method in which the above code is not publicly accessible from 
 the internet, you can use the example in `deploy.php`. The script should require 
 the input of a secret key or password to run the deploy() function. 
 4. Test the deployment by visiting the script url in your browser.
 5. Once happy, follow the steps of setting up a hook. See GitHub, BitBucket or 
 Generic Git for examples.

### GitHub

 1. _(This step is only needed for private repositories)_ Go to
    `https://github.com/USERNAME/REPOSITORY/settings/keys` and add your server
    SSH key.
 1. Go to `https://github.com/USERNAME/REPOSITORY/settings/hooks`.
 1. Click **Add webhook** in the **Webhooks** panel.
 1. Enter the **URL** for your deployment script e.g. `https://domain.com/deploy.php?secret=changeme`.
 1. _Optional_ Choose which events should trigger the deployment.
 1. Make sure that the **Active** checkbox is checked.
 1. Click **Add webhook**.

### Bitbucket

 1. _(This step is only needed for private repositories)_ Go to
    `https://bitbucket.org/USERNAME/REPOSITORY/admin/deploy-keys` and add your
    server SSH key.
 1. Go to `https://bitbucket.org/USERNAME/REPOSITORY/admin/services`.
 1. Add **POST** service.
 1. Enter the URL to your deployment script e.g. `https://domain.com/deploy.php?secret=changeme`.
 1. Click **Save**.

### Generic Git

 1. Configure the SSH keys.
 1. Add an executable `.git/hooks/post_receive` script that calls the script e.g.

```sh
#!/bin/sh
wget -q -O /dev/null https://domain.com/deploy.php?sat=changeme
```

## You're Done!

Next time you push the code to the repository that has a hook enabled, it's
going to trigger the `deploy.php` script which is going to pull the changes and
update the code on the _server machine_.

For more info, read the source of `deploy.php` and `src/GitDeploy.php`.

---

If you find this script useful, consider donating to PayPal `richard@webfwd.co.uk`.