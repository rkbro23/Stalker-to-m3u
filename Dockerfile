FROM php:8.2-apache

# Copy everything
COPY . /var/www/html/

# Enable Apache modules
RUN a2enmod rewrite headers

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port
EXPOSE 80

# Create uptime trigger
RUN echo "<?php http_response_code(200); echo 'online'; ?>" > /var/www/html/index.php

# Start Apache
CMD ["apache2-foreground"]
