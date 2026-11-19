# Image van het technisch register: Apache met PHP (mod_php) en de code uit src/.
#
# De container draait rootless, als www-data (uid 33), en luistert op poort 8080.
# Alles wat het register schrijft, staat in /var/www/geostandaarden/v2: koppel daar
# blijvende opslag aan. Bij elke start zet de container de code uit dit image in
# production/ van die opslag; de gepubliceerde artefacten blijven staan.
# Zie docker/README.md voor de instellingen.

# Het basisimage staat vast op een digest; Dependabot opent een pull request bij een nieuwe.
FROM php:8.5-apache@sha256:08af36df144462d84c7f16190557eec06cb9f656882685610b712a15eec54d46

LABEL org.opencontainers.image.title="Technisch register" \
      org.opencontainers.image.description="Technisch register voor geo-standaarden (register.geostandaarden.nl)" \
      org.opencontainers.image.source="https://github.com/Geonovum/technisch-register-2019" \
      org.opencontainers.image.licenses="MIT"

# PHP-extensie zip (ZipArchive) voor het uitpakken van releases. openssl zit al in PHP.
# Alleen de bibliotheken die de extensie nodig heeft, blijven in het image.
RUN set -eux; \
    savedAptMark="$(apt-mark showmanual)"; \
    apt-get update; \
    apt-get install -y --no-install-recommends libzip-dev; \
    docker-php-ext-install zip; \
    apt-mark auto '.*' > /dev/null; \
    [ -z "$savedAptMark" ] || apt-mark manual $savedAptMark; \
    find /usr/local/lib/php/extensions -name '*.so' -exec ldd '{}' ';' \
        | awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) { next }; gsub("^/(usr/)?", "", so); printf "*%s\n", so }' \
        | sort -u | xargs -r dpkg-query --search | cut -d: -f1 | sort -u | xargs -r apt-mark manual; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    rm -rf /var/lib/apt/lists/*; \
    php -m | grep -qx zip

COPY docker/php-register.ini /usr/local/etc/php/conf.d/register.ini
RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

# Apache: poort 8080 (rootless kan geen poort onder 1024 openen), mod_rewrite, de vhost
# van het register met de regels uit conf/apache2/, en geen versienummers in headers.
COPY conf/apache2/rewrite-rules.txt conf/apache2/artefact-directories.txt /etc/apache2/register/
COPY docker/apache-register.conf /etc/apache2/sites-available/register.conf
RUN set -eux; \
    sed -i 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf; \
    grep -qx 'Listen 8080' /etc/apache2/ports.conf; \
    sed -i -e 's/^ServerTokens .*/ServerTokens Prod/' -e 's/^ServerSignature .*/ServerSignature Off/' /etc/apache2/conf-available/security.conf; \
    echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf; \
    a2enconf servername; \
    a2enmod rewrite; \
    a2dissite 000-default; \
    a2ensite register; \
    mkdir -p /var/run/apache2 /var/lock/apache2 /var/www/geostandaarden/v2; \
    chown -R www-data:www-data /var/run/apache2 /var/lock/apache2 /var/www/geostandaarden/v2

# De code staat alleen-lezen in het image; de entrypoint zet hem bij de start in de opslag.
COPY src/ /opt/register/src/
COPY docker/entrypoint.sh /usr/local/bin/register-entrypoint
RUN chmod 0755 /usr/local/bin/register-entrypoint

# Het platform maakt 's nachts zelf back-ups; het register bewaart daarom alleen de vorige
# versie van elke standaard, wat ruimte spaart op de opslag.
ENV REGISTER_BACKUPS_TO_KEEP=1

USER 33:33
EXPOSE 8080
VOLUME ["/var/www/geostandaarden/v2"]
ENTRYPOINT ["/usr/local/bin/register-entrypoint"]
CMD ["apache2-foreground"]
