FROM php:7.2-fpm-stretch as base

ARG project_root=.

# install git for computing diffs
RUN apt-get update && apt-get install -y git wget gnupg g++ libicu-dev libpq-dev

# Install NodeJS
RUN curl -sL https://deb.nodesource.com/setup_10.x | bash
RUN apt-get install -y nodejs

# install Composer
COPY ${project_root}/docker/php-fpm/docker-install-composer /usr/local/bin/docker-install-composer
RUN chmod +x /usr/local/bin/docker-install-composer && docker-install-composer

# libpng-dev needed by "gd" extension
# libzip-dev needed by "zip" extension
# autoconf needed by "redis" extension
RUN apt-get install -y \
    libpng-dev \
    libzip-dev \
    autoconf

# install necessary PHP extensions requested by Composer
RUN docker-php-ext-install \
    bcmath \
    gd \
    intl \
    opcache \
    pgsql \
    pdo_pgsql \
    zip

# install Redis using pecl.
RUN pecl install redis-4.0.2 && docker-php-ext-enable redis

# install grunt-cli using npm to be able to run grunt watch
RUN npm install -g grunt-cli

# Install postgresql using wget
RUN wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | apt-key add - && \
    sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt/ $(lsb_release -sc)-pgdg main" > /etc/apt/sources.list.d/PostgreSQL.list' && \
    apt-get update && apt-get install -y postgresql-10 postgresql-client-10

# install locales and switch to en_US.utf8 in order to enable UTF-8 support
# see http://jaredmarkell.com/docker-and-locales/ from where was this code taken
RUN sed -i -e 's/# en_US.UTF-8 UTF-8/en_US.UTF-8 UTF-8/' /etc/locale.gen && \
    locale-gen
ENV LANG en_US.UTF-8
ENV LANGUAGE en_US:en
ENV LC_ALL en_US.UTF-8

# overwrite the original entry-point from the PHP Docker image with our own
COPY ${project_root}/docker/php-fpm/docker-php-entrypoint /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-php-entrypoint

# copy php.ini configuration
COPY ${project_root}/docker/php-fpm/php-ini-overrides.ini /usr/local/etc/php/php.ini

# the user "www-data" is used when running the image, and therefore should own the workdir
RUN usermod -m -d /home/www-data www-data
RUN chown www-data:www-data /home/www-data /var/www/html
USER www-data

# hirak/prestissimo makes the install of Composer dependencies faster by parallel downloading
RUN composer global require hirak/prestissimo

# set COMPOSER_MEMORY_LIMIT to -1 (i.e. unlimited - this is a hotfix until https://github.com/shopsys/shopsys/issues/634 is solved)
ENV COMPOSER_MEMORY_LIMIT=-1

########################################################################################################################

FROM base as development

USER root

# allow overwriting UID and GID o the user "www-data" to help solve issues with permissions in mounted volumes
# if the GID is already in use, we will assign GID 82 instead (82 is the standard uid/gid for "www-data" in Alpine)
ARG www_data_uid
ARG www_data_gid
RUN if [ -n "$www_data_uid" ]; then deluser www-data && (addgroup --gid $www_data_gid www-data || addgroup --gid 82 www-data) && adduser --system --home /home/www-data --uid $www_data_uid --disabled-password --gid $www_data_gid www-data; fi;

# as the UID and GID might have changed, change the ownership of the home directory workdir again
RUN chown -R www-data:www-data /home/www-data /var/www/html

USER www-data

########################################################################################################################

FROM base as production

COPY --chown=www-data:www-data / /var/www/html

RUN composer install --optimize-autoloader --no-interaction --no-progress --no-dev

RUN php phing composer npm dirs-create assets

########################################################################################################################

FROM base as ci

COPY --chown=www-data:www-data / /var/www/html

RUN composer install --optimize-autoloader --no-interaction --no-progress --dev

RUN php phing composer-dev npm dirs-create test-dirs-create assets standards tests-static tests-acceptance-build

RUN ./bin/console shopsys:environment:change prod
