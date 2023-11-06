FROM php:8.2-cli-bookworm
MAINTAINER Sergio Vale <segseven@gmail.com>

WORKDIR /app

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN set -x && apt-get update && apt-get install -y \
    wget \
    ffmpeg

#RUN wget -P /tmp 'https://download.tsi.telecom-paristech.fr/gpac/new_builds/gpac_latest_head_linux64.deb' && \
#    apt install /tmp/gpac_latest_head_linux64.deb

RUN chmod +x /usr/local/bin/install-php-extensions && sync && install-php-extensions \
    xdebug \
    @composer

#conf
ARG HOSTPATH
RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini
ADD conf.d /usr/local/etc/php/conf.d/
RUN sed -Eie 's|memory_limit = 128M|memory_limit = 256M|g' /usr/local/etc/php/php.ini
RUN sed -i -r "s|%hostpath%|${HOSTPATH}|g" /usr/local/etc/php/conf.d/xdebug.ini

EXPOSE 9000
