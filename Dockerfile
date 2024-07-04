FROM php:8.0-apache

RUN apt-get update && apt-get install -y cron libzip-dev zip wget
RUN docker-php-ext-install zip
RUN a2enmod headers && service apache2 restart

RUN mkdir /app

# RUN wget https://github.com/aws/aws-sdk-php/releases/download/3.315.5/aws.phar -O /app/aws.phar
COPY src/app/aws.phar /app/aws.phar
COPY src/app/verify-update.php /app/verify-update.php

# cron every hour verify-update.php
RUN echo "0 * * * * php /app/verify-update.php" > /etc/cron.d/verify-update
RUN chmod 0644 /etc/cron.d/verify-update
RUN crontab /etc/cron.d/verify-update
RUN touch /var/log/cron.log

EXPOSE 80

CMD php /app/verify-update.php && cron && apache2-foreground
