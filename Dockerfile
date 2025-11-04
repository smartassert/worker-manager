FROM php:8.3-fpm

WORKDIR /app

ARG APP_ENV=prod
ARG DATABASE_URL=postgresql://database_user:database_password@0.0.0.0:5432/database_name?serverVersion=12&charset=utf8
ARG MACHINE_NAME_PREFIX=dev
ARG PRIMARY_DIGITALOCEAN_API_TOKEN=primary_digitalocean_api_token
ARG SECONDARY_DIGITALOCEAN_API_TOKEN=secondary_digitalocean_api_token
ARG DIGITALOCEAN_REGION=lon1
ARG DIGITALOCEAN_SIZE=s-1vcpu-1gb
ARG WORKER_IMAGE=ubuntu-22-04-x64
ARG DIGITALOCEAN_TAG=worker
ARG CREATE_RETRY_LIMIT=3
ARG GET_RETRY_LIMIT=10
ARG DELETE_RETRY_LIMIT=10
ARG FIND_RETRY_LIMIT=3
ARG MACHINE_IS_ACTIVE_DISPATCH_DELAY=10000
ARG VERSION=dockerfile_version
ARG IS_READY=0
ARG AUTHENTICATION_BASE_URL=https://users.example.com

ENV APP_ENV=$APP_ENV
ENV DATABASE_URL=$DATABASE_URL
ENV MESSENGER_TRANSPORT_DSN=doctrine://default
ENV MACHINE_NAME_PREFIX=$MACHINE_NAME_PREFIX
ENV PRIMARY_DIGITALOCEAN_API_TOKEN=$PRIMARY_DIGITALOCEAN_API_TOKEN
ENV SECONDARY_DIGITALOCEAN_API_TOKEN=$SECONDARY_DIGITALOCEAN_API_TOKEN
ENV DIGITALOCEAN_REGION=$DIGITALOCEAN_REGION
ENV DIGITALOCEAN_SIZE=$DIGITALOCEAN_SIZE
ENV WORKER_IMAGE=$WORKER_IMAGE
ENV DIGITALOCEAN_TAG=$DIGITALOCEAN_TAG
ENV CREATE_RETRY_LIMIT=$CREATE_RETRY_LIMIT
ENV GET_RETRY_LIMIT=$GET_RETRY_LIMIT
ENV DELETE_RETRY_LIMIT=$DELETE_RETRY_LIMIT
ENV FIND_RETRY_LIMIT=$FIND_RETRY_LIMIT
ENV MACHINE_IS_ACTIVE_DISPATCH_DELAY=$MACHINE_IS_ACTIVE_DISPATCH_DELAY
ENV VERSION=$VERSION
ENV IS_READY=$READY
ENV USERS_SECURITY_BUNDLE_BASE_URL=$AUTHENTICATION_BASE_URL
ENV AUTHENTICATION_BASE_URL=$AUTHENTICATION_BASE_URL

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apt-get -qq update && apt-get -qq -y install  \
  libpq-dev \
  libzip-dev \
  supervisor \
  zip \
  && docker-php-ext-install \
  pdo_pgsql \
  zip \
  && apt-get autoremove -y \
  && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN mkdir -p var/log/supervisor
COPY build/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY build/supervisor/conf.d/app.conf /etc/supervisor/conf.d/supervisord.conf

COPY composer.json symfony.lock /app/
COPY bin/console /app/bin/console
COPY public/index.php public/
COPY src /app/src
COPY config/bundles.php config/routes.yaml config/services.yaml /app/config/
COPY config/packages/*.yaml /app/config/packages/
COPY config/packages/prod /app/config/packages/prod
COPY migrations /app/migrations

RUN chown -R www-data:www-data /app/var/log \
  && echo "APP_SECRET=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 32 | head -n 1)" > .env \
  && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-scripts \
  && rm composer.lock \
  && php bin/console cache:clear \
  && printenv | grep "^WORKER_IMAGE" \
  && printenv | grep "^VERSION"

CMD supervisord -c /etc/supervisor/supervisord.conf
