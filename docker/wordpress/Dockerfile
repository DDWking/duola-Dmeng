FROM wordpress:php8.3-apache

RUN set -eux; \
    a2enmod rewrite headers expires

COPY wordpress/wp-content/themes/duola-pocket/ /usr/src/wordpress/wp-content/themes/duola-pocket/
COPY wordpress/wp-content/plugins/duola-albums/ /usr/src/wordpress/wp-content/plugins/duola-albums/
COPY docker/wordpress/uploads.ini $PHP_INI_DIR/conf.d/uploads.ini

RUN chown -R www-data:www-data /usr/src/wordpress/wp-content/themes/duola-pocket /usr/src/wordpress/wp-content/plugins/duola-albums
