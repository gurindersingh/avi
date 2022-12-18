#!/bin/bash

CURRENT_RELEASE="{{ $currentRelease }}"

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
}

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

