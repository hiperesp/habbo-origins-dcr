FROM php:8.0-apache

RUN apt-get update && apt-get install -y cron libzip-dev zip
RUN docker-php-ext-install zip
RUN a2enmod headers && service apache2 restart

# cron every hour verify-update.php
RUN echo "0 * * * * php /app/verify-update.php" > /etc/cron.d/verify-update
RUN chmod 0644 /etc/cron.d/verify-update
RUN crontab /etc/cron.d/verify-update
RUN touch /var/log/cron.log

WORKDIR /var/www/html/
COPY src/data/ .
COPY src/verify-update.php /app/verify-update.php

RUN chmod 777 -R /var/www/html

EXPOSE 80

CMD php /app/verify-update.php && cron && apache2-foreground
