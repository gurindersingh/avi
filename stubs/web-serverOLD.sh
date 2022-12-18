#!/bin/bash

CURRENT_RELEASE="{{ $currentRelease }}"

add_git_config() {
cat > /home/ubuntu/.gitconfig << EOF
[user]
    name = Apsonex Inc.
    email = apsonexinc@gmail.com
EOF
}

add_ssh_config() {
# Add SSH Config
cat > /home/ubuntu/.ssh/config << EOF
Host github.com
    HostName github.com
    Preferredauthentications publickey
    IdentityFile /home/ubuntu/.ssh/id_github_apsonex
EOF
}

add_ssh_keys() {
# Add SSH Key
cat > /home/ubuntu/.ssh/id_github_apsonex << EOF
{{ $id_github_apsonex }}
EOF
cat > /home/ubuntu/.ssh/id_github_apsonex.pub << EOF
{{ $id_github_apsonex_public }}
EOF
    chmod 400 /home/ubuntu/.ssh/id_github_apsonex
    chmod 400 /home/ubuntu/.ssh/id_github_apsonex.pub
    echo '' > /home/ubuntu/.ssh/known_hosts
    # Copy Source Control Public Keys Into Known Hosts File
    ssh-keyscan -H github.com >> /home/ubuntu/.ssh/known_hosts
    ssh-keyscan -H bitbucket.org >> /home/ubuntu/.ssh/known_hosts
    ssh-keyscan -H gitlab.com >> /home/ubuntu/.ssh/known_hosts
}

add_composer_auth_key() {
mkdir -p /home/ubuntu/.composer
touch /home/ubuntu/.composer/auth.json
cat > /home/ubuntu/.composer/auth.json << EOF
{
    "github-oauth": {
        "github.com": "{{ $composerGithubToken }}"
    }
}
EOF
# COMPOSER_AUTH='{\"github-oauth\": {\"github.com\": \"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\"}}'
#echo "COMPOSER_AUTH='{\"github-oauth\": {\"github.com\": \"{{ $composerGithubToken }}\"}}'" | sudo tee /etc/environment
}

cleanup_old_releases() {
    cd /home/ubuntu/{{ $appName }}/releases
    ls -A | sort  | head -n -{{ $backup_count }}  | xargs rm -rf

    cd /home/ubuntu/{{ $appName }}/deployments
    ls -A | sort  | head -n -{{ $backup_count }}  | xargs rm -rf
}

reload_supervisor() {
    if sudo supervisorctl version 2>/dev/null; then
        sudo supervisorctl reread
        sudo supervisorctl update
        sudo supervisorctl restart all
    fi
}

restart_ssr_server() {
    if [ -f /home/ubuntu/{{ $appName }}/current/bootstrap/ssr/ssr.mjs ]; then
        # ln -sf /home/ubuntu/{{ $appName }}/current/bootstrap/ssr/ssr.mjs /home/ubuntu/ssr/runner
        sudo systemctl status ssr;
        if [ $? -eq 0 ]; then;
            # ssr running
            sudo systemctl restart ssr
        else;
            # ssr stopped need to run
            sudo systemctl start ssr
        fi;
    else

    fi
    # supervisorctl status // to list processes
    # supervisorctl start ssr_config // to start
    # sudo supervisorctl start ssr_config
    # sudo supervisorctl restart ssr_config
    #if [ -f /home/ubuntu/{{ $appName }}/releases/$CURRENT_RELEASE/bootstrap/ssr/ssr.mjs ]; then
    #    if [ -f /home/ubuntu/ssr/runner.sh ]; then
    #
    #    fi
     # systemctl status dknkdv; if [ $? -eq 0 ]; then; echo OK; else; echo FAIL; fi;
}


if [ ! -f /home/ubuntu/.ssh/id_github_apsonex.pub ]; then
    add_git_config
    add_ssh_config
    add_ssh_keys
    add_composer_auth_key
fi

# Make directories
mkdir -p /home/ubuntu/{{ $appName }}/{releases,storage,stages,deployments}

mkdir -p /home/ubuntu/{{ $appName }}/storage/{app/public,logs,framework/cache,framework/sessions,framework/testing,framework/views}

mkdir -p /home/ubuntu/{{ $appName }}/releases/$CURRENT_RELEASE

cd /home/ubuntu/{{ $appName }}/releases/$CURRENT_RELEASE

# Clone repository to remote servers
git clone {{ $gitRepo}} . --config core.sshCommand="ssh -i {{ $sshKeyPath }}"
git checkout {{ $gitBranch }}

# touch ./database/database.sqlite
cp /home/ubuntu/{{ $appName }}/deployments/$CURRENT_RELEASE/.env .
mkdir -p ./storage/{app/public,logs,framework/cache,framework/sessions,framework/testing,framework/views}
composer install --optimize-autoloader --no-dev
npm install
npm run build
# sqlite file
touch /home/ubuntu/{{ $appName }}/releases/$CURRENT_RELEASE/database/database.sqlite
rm -rf ./storage
ln -sfn /home/ubuntu/{{ $appName }}/storage .
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
#php artisan config:cache
#php artisan event:cache
#php artisan route:cache
#php artisan view:cache
ln -sfn /home/ubuntu/{{ $appName }}/releases/$CURRENT_RELEASE /home/ubuntu/{{ $appName }}/current

restart_ssr_server

#sudo service php8.1-fpm reload
sudo service php{{ $phpVersion }}-fpm reload

cleanup_old_releases
reload_supervisor

#GIT_SSH_COMMAND='ssh -i ../id_rsa -o IdentitiesOnly=yes' git clone git@github.com:apsonex/apsonex.git
#chmod 600 id_rsa

