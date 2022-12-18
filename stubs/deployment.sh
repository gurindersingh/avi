#!/bin/bash

CURRENT_RELEASE="{{ $currentRelease }}"

################################################
# Make required Directories
################################################
mkdir -p /home/ubuntu/{{ $appName }}/{releases,storage,deployments}
mkdir -p /home/ubuntu/{{ $appName }}/storage/{app/public,logs,framework/cache,framework/sessions,framework/testing,framework/views}
mkdir -p /home/ubuntu/{{ $appName }}/releases/{{ $currentRelease }}

################################################
# Make SSH File to clone repo from github
################################################
cat > /home/ubuntu/{{ $appName }}/deployments/{{ $currentRelease }}/id_rsa << EOF
{{ $sshPrivateKeyContent }}
EOF
chmod 400 /home/ubuntu/{{ $appName }}/deployments/{{ $currentRelease }}/id_rsa

################################################
# Clone git repo in current release folder
################################################
cd /home/ubuntu/{{ $appName }}/releases/{{ $currentRelease }}
GIT_SSH_COMMAND='ssh -i /home/ubuntu/{{ $appName }}/deployments/{{ $currentRelease }}/id_rsa -o IdentitiesOnly=yes' git clone {{ $gitRepo }} .
git checkout {{ $gitBranch }}


################################################
# Copy .env file
################################################
cd /home/ubuntu/{{ $appName }}/releases/{{ $currentRelease }}
cp /home/ubuntu/{{ $appName }}/deployments/{{ $currentRelease }}/.env .

################################################
# Composer install
################################################
cd /home/ubuntu/{{ $appName }}/releases/{{ $currentRelease }}
mkdir -p ./storage/{app/public,logs,framework/cache,framework/sessions,framework/testing,framework/views}

cd /home/ubuntu/{{ $appName }}/releases/{{ $currentRelease }}
COMPOSER_AUTH='{"github-oauth": {"github.com": "{{ $githubToken }}"}}' composer install --optimize-autoloader --no-dev

################################################
# install npm dependencies
################################################
npm install
npm run build

################################################
# Laravel Optimize
################################################
php artisan optimize:clear

################################################
# Release New
################################################
rm -rf ./storage
ln -sfn /home/ubuntu/{{ $appName }}/storage .
php artisan optimize
ln -sfn /home/ubuntu/{{ $appName }}/releases/{{ $currentRelease }} /home/ubuntu/{{ $appName }}/current

################################################
# Release New
################################################
php artisan migrate --force

################################################
# Reload SSR Server
################################################
if [ -f /home/ubuntu/{{ $appName }}/current/bootstrap/ssr/ssr.mjs ]; then
    sudo systemctl status {{ $appName }}-ssr;
    if [ $? -eq 0 ]; then;
        # ssr running
        sudo systemctl restart {{ $appName }}-ssr
    else;
        # ssr stopped need to run
        sudo systemctl start {{ $appName }}-ssr
    fi;
else
fi

################################################
# Reload Services - Supervisor & PHP FPM
################################################
sudo service php{{ $phpVersion }}-fpm reload
if sudo supervisorctl version 2>/dev/null; then
    sudo supervisorctl reread
    sudo supervisorctl update
    sudo supervisorctl restart all
fi

################################################
# Cleanup old releases
################################################
cd /home/ubuntu/{{ $appName }}/releases
ls -A | sort  | head -n -{{ $backupCount }}  | xargs rm -rf

cd /home/ubuntu/{{ $appName }}/deployments
ls -A | sort  | head -n -{{ $backupCount }}  | xargs rm -rf
