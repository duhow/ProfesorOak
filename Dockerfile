FROM php:8.0.30-apache
#FROM php:8.3.4-apache

RUN apt-get update && apt-get install -y libmemcached-dev libssl-dev zlib1g-dev \
	  && pecl install memcached-3.2.0 \
	  && docker-php-ext-enable memcached \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install mysqli && \
    docker-php-ext-enable mysqli
