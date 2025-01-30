# LARAVEL - Custom configurations

To install the package, run the following command:
```
composer require roy404/laravel-commands
```

## Available Custom Commands

1. **Set Up the Custom Commands Provider** <br>
   To integrate the custom commands provider into your application, follow these steps:
   * Navigate to `bootstrap/providers.php`.
   * Add the following line to the array of providers:
   ```php
   Roy404\ArtisanCommands\ArtisanCommandsServiceProvider::class
   ```

2. **Setup Custom Authentication** <br>
   Generates authentication scaffolding with roles, admin, and user routes by default:

   ```shell
   php artisan custom:auth
   ```

3. **Clear the cache:** After adding the new provider, you should clear the cache and config cache to make sure your changes are applied:
   ```shell
   php artisan config:clear
   php artisan cache:clear 
   ```

4. **Setup Docker Environment:** <br>
   Builds the project container with Docker, including MySQL, Memcached, phpMyAdmin, and Xdebug:

   ```shell
    php artisan custom:docker
    ```

   After the docker configurations installed, run the following command below to generate new app key:
   ```shell
   php artisan key:generate
   ```

   You can now build the image:
   ```shell
   docker-compose up --build
   ```