FROM php:8.2-apache

# Cài đặt chứng chỉ SSL CA và extension pdo_mysql
RUN apt-get update && apt-get install -y ca-certificates libcurl4-openssl-dev \
    && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install curl pdo pdo_mysql
RUN printf "upload_max_filesize=20M\npost_max_size=128M\nmax_file_uploads=30\nmax_input_time=120\n" \
    > /usr/local/etc/php/conf.d/uploads.ini

# Bật mod_rewrite của Apache
RUN a2enmod rewrite

# Cấu hình Apache nhận cổng dynamic của Render ($PORT mặc định 10000)
ENV PORT=80
RUN sed -i "s/80/\${PORT}/g" /etc/apache2/ports.conf /etc/apache2/sites-available/*.conf

# Copy toàn bộ mã nguồn vào thư mục web của Apache
COPY . /var/www/html/

# Phân quyền thư mục uploads để PHP có thể ghi/upload file
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 777 /var/www/html/uploads

EXPOSE ${PORT}
