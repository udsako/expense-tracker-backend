FROM php:8.3-cli

# Install PHP extensions + Poppler (pdftoppm) + Tesseract OCR
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libonig-dev libxml2-dev libpq-dev \
    poppler-utils tesseract-ocr \
    && docker-php-ext-install pdo_mysql pdo_pgsql mbstring zip bcmath

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --no-dev --optimize-autoloader

EXPOSE 10000
CMD php artisan config:clear && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=10000
