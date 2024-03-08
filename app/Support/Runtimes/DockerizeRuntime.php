<?php

namespace App\Support\Runtimes;

class DockerizeRuntime extends BaseRuntime
{

    protected function process()
    {
        if (!$this->isWeb() || !$this->dto()->isFrankephpServer()) return;

        $this->addContent('dockerize_for_web', $this->content());
    }


    protected function content(): string
    {
        $string = <<<'BLADE'
#cd /home/ubuntu/{{ $domain }}/releases/{{ $newRelease }}
cd /home/ubuntu/{{ $domain }}

#rm -rf ./storage
#rm -rf ./node_modules
#rm -rf ./tests
#rm -rf ./*.md

cat > /home/ubuntu/{{ $domain }}/Dockerfile << EOF
FROM dunglas/frankenphp:1.1-builder-php8.2.16-bookworm AS base

# Set Caddy server name to "http://" to serve on 80 and not 443
# Read more: https://frankenphp.dev/docs/config/#environment-variables
ENV SERVER_NAME="http://"

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
    git \
    unzip \
    librabbitmq-dev \
    libpq-dev

RUN install-php-extensions \
	pdo_mysql \
	gd \
	intl \
    imap \
    bcmath \
    redis \
    curl \
    exif \
    hash \
    iconv \
    json \
    mbstring \
    mysqli \
    mysqlnd \
    pcntl \
    pcre \
    xml \
    libxml \
    zlib \
	zip

# https://github.com/mlocati/docker-php-extension-installer#supported-php-extensions
RUN install-php-extensions \
    imagick

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

RUN mv "\$PHP_INI_DIR/php.ini-production" "\$PHP_INI_DIR/php.ini" \
    && mkdir vendor \
    && chown www-data:www-data vendor

COPY frankenphp/proexpert.ini \$PHP_INI_DIR/conf.d/

COPY frankenphp/opcache.ini \$PHP_INI_DIR/conf.d/

COPY ./Caddyfile /app/

RUN install-php-extensions \
    sockets

RUN mkdir /proexpert.config

COPY entrypoint.sh /proexpert.config/entrypoint.sh

RUN chmod +x /proexpert.config/entrypoint.sh

# COPY --chown=www-data:www-data ./releases/{{ $newRelease }} /app/

# ENV COMPOSER_ALLOW_SUPERUSER=1

# RUN composer install --prefer-dist --optimize-autoloader --no-dev --no-scripts

# RUN mkdir -p \
#     /app/storage/app \
#     /app/storage/framework/cache \
#     /app/storage/framework/views \
#     /app/storage/framework/sessions \
#     /app/storage/logs && \
#     touch /app/storage/logs/laravel.log

# VOLUME /app/storage

# RUN chown -R www-data:www-data \
#     /app/storage/logs \
#     /app/storage/app \
#     /app/storage/framework \
#     /app/storage/framework/views \
#     /app/storage/framework/sessions \
#     /app/bootstrap/cache

# RUN  chmod -R 775 /app/storage/logs

# RUN php artisan optimize:clear && php artisan optimize

# frankenphp run --config /etc/caddy/Caddyfile --adapter caddyfile

# ENTRYPOINT ["php", "artisan", "octane:frankenphp"]

ENTRYPOINT ["/proexpert.config/entrypoint.sh"]

EOF

cd /home/ubuntu/{{ $domain }}

docker build -t  apxprox-web:latest -f $PWD/Dockerfile .

docker stop apxprox-web-instance

docker rm apxprox-web-instance

docker run -d \
    -p 80:8000 \
    --name=apxprox-web-instance \
    --restart=unless-stopped \
    -v ./releases/{{ $newRelease }}:/app \
    -v ./storage:/app/storage \
    apxprox-web:latest

docker image prune -a --filter "until=60m" --force

docker container prune --force

BLADE;

        return $this->compileBlade($string, [
            'domain' => $this->dto()->domain,
            'newRelease' => $this->dto()->newRelease,
            'sshKeyContent' => $this->dto()->sshKeyContent,
            'gitUser' => $this->dto()->gitUser,
            'repo' => $this->dto()->repo,
            'gitToken' => $this->dto()->gitToken,
            'gitBranch' => $this->dto()->gitBranch,
        ]);
    }
}
