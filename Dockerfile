FROM php:8.2-cli

RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www/html
COPY . .

RUN mkdir -p uploads && chmod 777 uploads

ENV PORT=8080
EXPOSE 8080

CMD php -S 0.0.0.0:${PORT} -t .
