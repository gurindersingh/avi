#!/bin/bash

CURRENT_RELEASE="{{ $currentRelease }}"

GREEN='\033[0;32m'
echo_green() {
    echo -e "${GREEN}${1}${NOCOLOR}"
}

BLUE='\033[0;34m'
echo_blue() {
    echo -e "${BLUE}${1}${NOCOLOR}"
}

PURPLE='\033[0;35m'
echo_purple() {
    echo -e "${PURPLE}${1}${NOCOLOR}"
}

WHITE='\033[1;37m'
echo_white() {
    echo -e "${WHITE}${1}${NOCOLOR}"
}


################################################
# Make required Directories
################################################
mkdir -p /home/ubuntu/{{ $appName }}/{releases,storage,deployments}
mkdir -p /home/ubuntu/{{ $appName }}/storage/{app/public,logs,framework/cache,framework/sessions,framework/testing,framework/views}
mkdir -p /home/ubuntu/{{ $appName }}/releases/{{ $currentRelease }}

################################################
# Make SSH File to clone repo from github
################################################
rm -rf ~/.ssh/known_hosts && \
    ssh-keyscan -H github.com >> ~/.ssh/known_hosts && \
	ssh-keyscan -H bitbucket.org >> ~/.ssh/known_hosts && \
	ssh-keyscan -H gitlab.com >> ~/.ssh/known_hosts

@if($gitDeploySshKeyContent)
cat > /home/ubuntu/{{ $appName }}/deployments/{{ $currentRelease }}/id_rsa << EOF
{{ $gitDeploySshKeyContent }}
EOF
chmod 400 /home/ubuntu/{{ $appName }}/deployments/{{ $currentRelease }}/id_rsa
rm -rf /home/ubuntu/.ssh/config
cat > /home/ubuntu/.ssh/config <<EOF
Host github.com
	User apsonex
	Hostname github.com
	PreferredAuthentications publickey
	IdentityFile /home/ubuntu/{{ $appName }}/deployments/{{ $currentRelease }}/id_rsa
EOF
@endif

################################################
# Clone git repo in current release folder
################################################
cd /home/ubuntu/{{ $appName }}/releases/{{ $currentRelease }}
@if($gitDeploySshKeyContent)
GIT_SSH_COMMAND='ssh -i /home/ubuntu/{{ $appName }}/deployments/{{ $currentRelease }}/id_rsa -o IdentitiesOnly=yes' git clone {{ $gitRepo }} .
@elseif($githubToken)
COMPOSER_AUTH='{"github-oauth": {"github.com": "{{ $githubToken }}"}}' \
    GH_TOKEN="{{ $githubToken }}" \
    gh repo clone {{ $gitRepo }} .
@else
git clone {{ $gitRepo }} .
@endif
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
@if(isset($composerAuthToken))
COMPOSER_AUTH='{"github-oauth": {"github.com": "{{ $composerAuthToken }}"}}' composer install --optimize-autoloader --no-dev
@else
composer install --optimize-autoloader --no-dev
@endif

################################################
# install npm dependencies
################################################
pnpm install
pnpm run build

################################################
# Laravel Optimize
################################################
echo_white "Running scripts after composer post install"
{{ $composerPostInstallScripts }}
#echo_white "Clearing Optimization..."
#php artisan optimize:clear
#echo_white "Migrating..."
#php artisan migrate --force
#echo_white "Seeding production..."
#php artisan fm:seed-production
#echo_white "Seeded..."

################################################
# Release New
################################################
echo_white "Releasing..."
rm -rf ./storage
ln -sfn /home/ubuntu/{{ $appName }}/storage .
ln -sfn /home/ubuntu/{{ $appName }}/releases/{{ $currentRelease }} /home/ubuntu/{{ $appName }}/current

################################################
# Post Release Scripts
################################################O
echo_white "Running post release scripts"
{{ $postReleaseScripts }}
#echo_white "Optimizing after post release..."
#php artisan optimize

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
#cd /home/ubuntu/{{ $appName }}/releases
#echo '------ old releases deleting'
#ls -A | sort  | head -n -{{ $backupCount }}  | xargs rm -rf

echo_blue "--- Removing old releases ---"
cd /home/ubuntu/{{ $appName }}/releases
PWD=$(pwd)
for OUTPUT in $(ls -A | sort  | head -n -{{ $backupCount }})
do
    if [ -d "$PWD/$OUTPUT" ]
    then
        echo "Removing release: $OUTPUT "
        rm -rf "$PWD/$OUTPUT"
    else
        echo "Release Directory does not exit: $PWD/$OUTPUT"
    fi
done

echo_blue "--- Removing old deployments ---"
cd /home/ubuntu/{{ $appName }}/deployments
PWD=$(pwd)
for OUTPUT in $(ls -A | sort  | head -n -{{ $backupCount }})
do
    if [ -d "$PWD/$OUTPUT" ]
    then
        echo "Removing deployments: $OUTPUT "
        rm -rf "$PWD/$OUTPUT"
    else
        echo "Deployment Directory does not exit: $PWD/$OUTPUT"
    fi
done

#cd /home/ubuntu/{{ $appName }}/deployments
#echo '------ old deployments deleting'
#ls -A | sort  | head -n -{{ $backupCount }}  | xargs rm -rf
#echo '------ old deployments deleted'

echo "Deployment finished. Current release id# {{ $currentRelease }}";
