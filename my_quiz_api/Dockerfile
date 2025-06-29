FROM php:8.1-apache

# Cài extension PHP để kết nối MySQL
RUN docker-php-ext-install mysqli

# Copy toàn bộ mã nguồn vào thư mục web server
COPY . /var/www/html/

# Set quyền cho thư mục
RUN chown -R www-data:www-data /var/www/html

# (Không bắt buộc) Bật mod_rewrite nếu bạn dùng route đẹp
RUN a2enmod rewrite

EXPOSE 80
