FROM yiisoftware/yii2-php:8.4-apache

# Generate default short-video covers from the fifth decoded frame.
RUN apt-get update && apt-get install -y --no-install-recommends ffmpeg \
    && rm -rf /var/lib/apt/lists/*

# Allow image collections to contain multiple files while keeping each file
# within the 10 MB limit enforced by the application.
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Change document root for Apache
RUN sed -i -e 's|/app/web|/app/frontend/web|g' /etc/apache2/sites-available/000-default.conf
