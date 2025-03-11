
FROM php:8.4-fpm-alpine
MAINTAINER Jorg Mertin <github_jm@solsys.org>

RUN passwd -l root && apk update --no-cache && apk --no-cache add supervisor bash sqlite \
php84-sqlite3 php84-gd php84-exif php84-gettext php84-pecl-igbinary  php84-pcntl php84-shmop \
php84-sockets php84-sysvmsg php84-sysvsem php84-sysvshm php84-pecl-xlswriter php84-zip php84-opcache \
nano bash mariadb-client libxslt zstd-libs libzip libintl gettext-libs libgd icu-libs lz4-libs tcpdump

RUN docker-php-ext-configure mysqli  \
    && docker-php-ext-install -j$(nproc) mysqli \
    && docker-php-source delete

ADD entrypoint.sh /
ADD install_mysqldb.sh /tmp
ADD whdc.sql /tmp

RUN  mkdir -p /var/www/logs /var/www/html/site && \
    chown -R root:www-data /var/www/html/site /var/www/logs && \
    chmod 775 /var/www/logs  && \
    mv -f /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini && \
    chmod 755 /entrypoint.sh /tmp/install_mysqldb.sh /var/www/html/site && \
    touch /var/www/logs/error.log /var/www/logs/access.log && \
    chown www-data:www-data /var/www/logs/error.log /var/www/logs/access.log

ADD src/style.css /tmp/
ADD LICENSE.md /tmp/
ADD release.txt /tmp/
ADD src/showhide.js /tmp/
ADD src/whdclist.php /tmp/
ADD src/whdctokens.php /tmp/
ADD src/whdc.php /tmp/
ADD src/auth.inc /tmp
ADD src/favicon.ico /tmp/
ADD src/webhook-logo.svg /tmp/
# COPY websocket-php.tar.gz /tmp/
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN cd /var/www/html && /usr/local/bin/composer require phrity/websocket && /usr/local/bin/composer require freedsx/snmp && chown www-data:www-data -R vendor && tar zcvf /var/www/html/php-websocket.tar.gz vendor
RUN chown www-data:www-data /tmp/*

USER www-data

CMD ["/entrypoint.sh"]
