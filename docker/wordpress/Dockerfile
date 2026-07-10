FROM wordpress:php8.3-apache

RUN set -eux; \
    a2enmod rewrite headers expires; \
    printf '%s\n' \
        '<Directory /var/www/html>' \
        '    AllowOverride None' \
        '    Require all granted' \
        '    FallbackResource /index.php' \
        '</Directory>' \
        > /etc/apache2/conf-available/duola-routes.conf; \
    a2enconf duola-routes

COPY wordpress/wp-content/themes/duola-pocket/ /usr/src/wordpress/wp-content/themes/duola-pocket/
COPY wordpress/wp-content/plugins/duola-albums/ /usr/src/wordpress/wp-content/plugins/duola-albums/
COPY docker/wordpress/uploads.ini $PHP_INI_DIR/conf.d/uploads.ini
COPY docker/wordpress/entrypoint.sh /usr/local/bin/duola-wordpress-entrypoint

RUN chown -R www-data:www-data /usr/src/wordpress/wp-content/themes/duola-pocket /usr/src/wordpress/wp-content/plugins/duola-albums; \
    chmod +x /usr/local/bin/duola-wordpress-entrypoint

ENTRYPOINT ["duola-wordpress-entrypoint"]
CMD ["apache2-foreground"]
