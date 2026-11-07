# SPDX-FileCopyrightText: 2026 EDALVIX
# SPDX-License-Identifier: GPL-2.0-or-later
#
# Edalvix Cloud update server: https://updates.edalvix.cloud
#   /client/    -> update check used by the desktop client (APPLICATION_UPDATE_URL)
#   /download/  -> installers, from the /srv/downloads volume
#
#   docker build -t edalvix-update-server .
#   docker run -d -p 8080:80 \
#     -v /srv/edalvix/releases.json:/srv/config/releases.json:ro \
#     -v /srv/edalvix/downloads:/srv/downloads:ro \
#     edalvix-update-server
FROM php:8.3-apache

ENV EDALVIX_RELEASES_FILE=/srv/config/releases.json

COPY src/ /srv/app/src/
COPY public/ /srv/app/public/
COPY config/releases.json /srv/config/releases.json
COPY apache.conf /etc/apache2/sites-available/000-default.conf

RUN mkdir -p /srv/downloads \
 && a2enmod headers >/dev/null

EXPOSE 80
