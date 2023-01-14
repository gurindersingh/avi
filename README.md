# Deploy laravel web app to remote servers

A simple composer package to deploy Laravel web app to remote Ubuntu servers.

## Install avi deployment tool
Install avi command line tool
```sh
composer global require gurindersingh/avi
```
You must have `avi` command line tool available now.

## Initialize the process
In your project directory run the following command
`avi deploy:init`

## Deploy environment
To deploy on particular environment, run deploy command with that environment.
For example, to deploy in staging environment run following command.
```shell
avi deploy:web environment # for environment
avi deploy:web staging # for staging
avi deploy:web production # for production
```
Make sure that you have config available for deployment environment in avi.json file.

## Exclude from .git
Add `.avi` & `avi.json` in .gitignore file to exclude from github 

## Add scripts to run after composer install
In avi.json
```sh
{
    ...,
    "staging": {
        ...,
        "composerPostInstallScripts" : [
            "php artisan optimize:clear",
            "php artisan migrate --force",
            "php artisan fm:seed-production"
        ]
    }
}
```

## Add scripts to run after new release is active
In avi.json
```sh
{
    ...,
    "production": {
        ...,
        "postReleaseScripts" : [
            "php artisan optimize"
        ]
    }
}
```
