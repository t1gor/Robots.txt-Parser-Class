FROM php:7.4-cli-alpine

# install git
RUN apk add --no-cache git

# install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
