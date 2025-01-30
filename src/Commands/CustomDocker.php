<?php

	namespace Roy404\ArtisanCommands\Commands;

	use Illuminate\Console\Command;
	use Illuminate\Support\Facades\File;

	class CustomDocker extends Command
	{
		protected $signature = 'custom:docker';

		protected $description = 'Setup the project container into the docker: memcache, mysql, phpmyadmin, xdebug';

		public function handle()
		{
			$this->info('Setting up Docker environment...');

			$this->createFile(base_path('docker'), $this->dockerArtisanAlias());
			$this->createFile(base_path('docker-compose.yml'), $this->dockerComposeContent());
			$this->createFile(base_path('Dockerfile'), $this->dockerfileContent());
			$this->createFile(base_path('xdebug.ini'), $this->xdebugConfig());
			$this->createFile(base_path('.env'), $this->envContent());

			$this->info('Docker setup completed successfully!');
		}

		private function createFile($path, $content): void
		{
			if (File::exists($path)) {
				File::delete($path);
			}
			File::put($path, $content);
			$this->info(basename($path) . ' created successfully.');
		}

		private function dockerArtisanAlias(): string
		{
			return <<<SCRIPT
			#!/usr/bin/env php
			<?php
				\$args = implode(' ', array_slice(\$argv, 1));
				\$command = "docker exec -it app_container php artisan \$args";
				\$output = shell_exec(\$command);
				echo \$output;
			SCRIPT;
		}

		private function dockerComposeContent(): string
		{
			return <<<YML
			services:
				app:
					build:
						context: ./
						dockerfile: Dockerfile
					container_name: app_container
					restart: unless-stopped
					ports:
						- "\${APP_PORT:-80}:80"
					volumes:
						- ./:/var/www/html
						- ./xdebug.ini:/usr/local/etc/php/conf.d/xdebug.ini
					networks:
						- project_network
					environment:
						VIRTUAL_HOST: \${APP_IP:-localhost}  # Optional, useful for reverse proxy setups
						APP_ENV: \${APP_ENV:-local}  # Make sure the app environment is set (optional)
						DB_HOST: mysql  # Connect to MySQL container by name
						DB_PORT: \${DB_PORT:-3306}  # Ensure the port for DB connection is correct
						DB_DATABASE: \${DB_DATABASE:-laravel}  # Laravel database name
						DB_USERNAME: \${DB_USERNAME:-admin}  # Database user
						DB_PASSWORD: \${DB_PASSWORD:-admin}  # Database password
			
				mysql:
					image: mysql:8
					container_name: mysql_container
					restart: unless-stopped
					environment:
						MYSQL_ROOT_PASSWORD: admin
						MYSQL_DATABASE: \${DB_DATABASE:-laravel}
						MYSQL_USER: \${DB_USERNAME:-admin}
						MYSQL_PASSWORD: \${DB_PASSWORD:-admin}
					ports:
						- "3306:3306"
					volumes:
						- mysql_data:/var/lib/mysql
					networks:
						- project_network
			
				phpmyadmin:
					image: phpmyadmin/phpmyadmin:latest
					container_name: phpmyadmin_container
					restart: unless-stopped
					environment:
						PMA_HOST: mysql
						PMA_PORT: \${DB_PORT:-3306}
						PMA_USER: \${DB_USERNAME:-admin}
						PMA_PASSWORD: \${DB_PASSWORD:-admin}
					ports:
						- "8080:80"
					networks:
						- project_network
			
				memcached:
					image: memcached:alpine
					container_name: memcached_container
					restart: unless-stopped
					ports:
						- "\${MEMCACHE_PORT:-11211}:11211"
					networks:
						- project_network
			
			networks:
				project_network:
					driver: bridge
			
			volumes:
				mysql_data:
			YML;
		}

		private function dockerfileContent(): string
		{
			return <<<SCRIPT
			# Use an official PHP 8.2 image with Apache
			FROM php:8.2-apache
			
			# Enable mod_rewrite for Apache
			RUN a2enmod rewrite
			
			# Update and install required dependencies
			RUN apt-get update && apt-get install -y --no-install-recommends \
				libbz2-dev libcurl4-nss-dev libxml2-dev libssl-dev libpng-dev libc-client-dev libkrb5-dev libxslt1-dev libzip-dev libonig-dev \
				libmemcached-dev libssh2-1-dev libmcrypt-dev \
				libwebp-dev libjpeg62-turbo-dev libxpm-dev libfreetype6-dev \
				&& apt-get clean \
				&& rm -rf /var/lib/apt/lists/*
			
			# GD extension
			RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-xpm --with-webp --enable-gd \
				&& docker-php-ext-install gd
			
			# IMAP extension
			RUN docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
				&& docker-php-ext-install imap
			
			# Install necessary PHP extensions
			RUN docker-php-ext-install -j$(nproc) mysqli pdo pdo_mysql bcmath bz2 calendar curl dom exif ftp gettext iconv intl mbstring opcache soap shmop sockets sysvmsg sysvsem sysvshm xsl zip
			
			# Install PECL extensions
			RUN pecl install xdebug \
				&& docker-php-ext-enable xdebug
			
			RUN pecl install igbinary \
				&& docker-php-ext-enable igbinary
			
			RUN pecl install msgpack \
				&& docker-php-ext-enable msgpack
			
			RUN pecl install mcrypt \
				&& docker-php-ext-enable mcrypt
			
			# Memcached extension
			RUN pecl install memcached --with-libmemcached-dir=/usr \
				&& docker-php-ext-enable memcached
			
			# SSH2 extension
			RUN pecl install ssh2 \
				&& docker-php-ext-enable ssh2
			
			# Clean up
			RUN docker-php-source delete
			
			# Allow .htaccess overrides
			RUN echo '<Directory /var/www/html>' > /etc/apache2/conf-available/htaccess.conf \
				&& echo '    AllowOverride All' >> /etc/apache2/conf-available/htaccess.conf \
				&& echo '</Directory>' >> /etc/apache2/conf-available/htaccess.conf \
				&& a2enconf htaccess
			
			# Set session save path
			RUN echo "session.save_path = /var/lib/php/sessions" > /usr/local/etc/php/conf.d/session.ini
			
			# Set the document root to the public folder
			RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf
			RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/default-ssl.conf
			
			# Copy your project files into the container's web directory
			COPY ./ /var/www/html
			
			# Expose port 80
			EXPOSE 80
			
			# Set the working directory (optional)
			WORKDIR /var/www/html
			
			# Restart Apache to apply changes
			RUN service apache2 restart
			SCRIPT;
		}

		private function xdebugConfig(): string
		{
			return <<<SCRIPT
			[xdebug]
			zend_extension=xdebug.so
			xdebug.mode=debug
			xdebug.start_with_request=yes
			xdebug.client_host=host.docker.internal
			xdebug.discover_client_host=0
			xdebug.client_port=9003
			xdebug.log=/tmp/xdebug.log
			xdebug.log_level=0
			SCRIPT;
		}

		private function envContent(): string
		{
			return <<<SCRIPT
			APP_NAME=Laravel
			APP_ENV=local
			APP_KEY=
			APP_DEBUG=true
			APP_TIMEZONE=UTC
			APP_URL=http://localhost
			APP_LOCALE=en
			
			DB_CONNECTION=mysql
			DB_HOST=127.0.0.1
			DB_PORT=3306
			DB_DATABASE=laravel
			DB_USERNAME=admin
			DB_PASSWORD=admin
			
			SESSION_DRIVER=database
			SESSION_LIFETIME=120
			SESSION_ENCRYPT=false
			SESSION_PATH=/
			SESSION_DOMAIN=null
			
			MEMCACHED_HOST=memcached
			MEMCACHED_PORT=11211
			
			CACHE_STORE=memcached
			CACHE_PREFIX=
			
			MAIL_MAILER=log
			MAIL_SCHEME=null
			MAIL_HOST=127.0.0.1
			MAIL_PORT=2525
			MAIL_USERNAME=null
			MAIL_PASSWORD=null
			MAIL_FROM_ADDRESS="hello@example.com"
			MAIL_FROM_NAME="\${APP_NAME}"
			
			LOG_CHANNEL=stack
			LOG_STACK=single
			LOG_DEPRECATIONS_CHANNEL=null
			LOG_LEVEL=debug
			
			BROADCAST_CONNECTION=log
			FILESYSTEM_DISK=local
			QUEUE_CONNECTION=database
			PHP_CLI_SERVER_WORKERS=4
			BCRYPT_ROUNDS=12
			SCRIPT;
		}
	}
