FROM php:8.3.37-apache
#FROM php:8.3.4-apache

RUN apt-get update && apt-get install -y libmemcached-dev libssl-dev zlib1g-dev \
	  && pecl install memcached-3.2.0 \
	  && docker-php-ext-enable memcached \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install mysqli && \
    docker-php-ext-enable mysqli

RUN printf '%s\n' \
    '<Directory "/var/www/html">' \
    '    <FilesMatch "^\.">' \
    '        Require all denied' \
    '    </FilesMatch>' \
    '    <FilesMatch "(^|/)(\.env|composer\.(json|lock)|package(-lock)?\.json|.*\.(bak|config|dist|ini|log|sql|sh|swp|ya?ml))$">' \
    '        Require all denied' \
    '    </FilesMatch>' \
    '</Directory>' \
    '<DirectoryMatch "^/var/www/html/(.*/)?\.[^/]+(/|$)">' \
    '    Require all denied' \
    '</DirectoryMatch>' \
    > /etc/apache2/conf-available/deny-sensitive-files.conf \
    && a2enconf deny-sensitive-files
