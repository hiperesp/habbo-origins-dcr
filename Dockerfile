FROM php:8.3

RUN apt-get update && apt-get install -y cron libzip-dev zip wget
RUN docker-php-ext-install zip

RUN mkdir /app

# RUN wget https://github.com/aws/aws-sdk-php/releases/download/3.315.5/aws.phar -O /app/aws.phar
COPY src/app/ /app/

CMD ["sh", "/app/loop.sh"]
