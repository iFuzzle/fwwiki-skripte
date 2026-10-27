FROM php:8.3-cli-bookworm

# Build-Abhaengigkeiten nur fuer die PHP-Erweiterungen, Laufzeitpakete bleiben.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        imagemagick fonts-dejavu-core \
        libfreetype6 libjpeg62-turbo libpng16-16 libzip4 \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev libcurl4-openssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd zip curl \
    && apt-get purge -y --auto-remove \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev libcurl4-openssl-dev \
    && rm -rf /var/lib/apt/lists/*

# Karten-Rendering braucht mehr als das PHP-Standardlimit.
RUN echo 'memory_limit=-1' > /usr/local/etc/php/conf.d/zz-karte.ini

WORKDIR /app
COPY gesamtkarte.sh makemap.php maplist.php ./

# Feldbild-Cache und Ergebnis liegen relativ zu /app und werden gemountet.
VOLUME ["/app/map_cache", "/app/ausgabe"]

ENTRYPOINT ["sh", "/app/gesamtkarte.sh"]
