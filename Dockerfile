FROM php:8.0-fpm-buster

WORKDIR /app

ARG APP_ENV=prod
ARG MACHINE_NAME_PREFIX=dev
ARG DIGITALOCEAN_API_TOKEN=digitalocean_api_token
ARG DIGITALOCEAN_REGION=lon1
ARG DIGITALOCEAN_SIZE=s-1vcpu-1gb
ARG WORKER_IMAGE=ubuntu-20-04-x64
ARG DIGITALOCEAN_TAG=worker
ARG CREATE_RETRY_LIMIT=3
ARG GET_RETRY_LIMIT=10
ARG DELETE_RETRY_LIMIT=10
ARG FIND_RETRY_LIMIT=3
ARG MACHINE_IS_ACTIVE_DISPATCH_DELAY=10000
ARG VERSION=dockerfile_version

ENV APP_ENV=$APP_ENV
ENV DATABASE_URL="sqlite:///%kernel.project_dir%/var/sqlite/data.db"
ENV MESSENGER_TRANSPORT_DSN=doctrine://default
ENV MACHINE_NAME_PREFIX=$MACHINE_NAME_PREFIX
ENV DIGITALOCEAN_API_TOKEN=$DIGITALOCEAN_API_TOKEN
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

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apt-get -qq update && apt-get -qq -y install  \
  libzip-dev \
  supervisor \
  zip \
  && docker-php-ext-install \
  zip \
  && apt-get autoremove -y \
  && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN mkdir -p var/log/supervisor
COPY build/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY build/supervisor/conf.d/app.conf /etc/supervisor/conf.d/supervisord.conf

COPY composer.json composer.lock /app/
COPY bin/console /app/bin/console
COPY public/index.php public/
COPY src /app/src
COPY config/bundles.php config/services.yaml /app/config/
COPY config/packages/*.yaml /app/config/packages/
COPY config/packages/prod /app/config/packages/prod
COPY config/routes/annotations.yaml /app/config/routes/

RUN chown -R www-data:www-data /app/var/log \
  && composer check-platform-reqs --ansi \
  && composer install --no-dev --no-scripts \
  && rm composer.lock \
  && touch /app/.env \
  && php bin/console cache:clear --env=prod \
  && mkdir -p /app/var/sqlite \
  && php bin/console doctrine:database:create \
  && php bin/console doctrine:schema:update --force \
  && chmod -R 0777 /app/var/sqlite \
  && printenv | grep "^WORKER_IMAGE" \
  && printenv | grep "^VERSION"

CMD supervisord -c /etc/supervisor/supervisord.conf
