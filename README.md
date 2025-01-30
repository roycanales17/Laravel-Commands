# LARAVEL - Custom configurations

To install the package, run the following command:
```
composer require roy404/laravel-commands
```

## Available Custom Commands

1. **Setup Docker Environment:** <br>
    Builds the project container with Docker, including MySQL, Memcached, phpMyAdmin, and Xdebug:
    
   ```shell
    php artisan custom:docker
    ```

   After the docker configurations installed, run the following command below to generate new app key:
   ```shell
   php artisan key:generate
   ```

2. **Setup Custom Authentication** <br>
   Generates authentication scaffolding with roles, admin, and user routes by default:

   ```shell
    php artisan custom:auth
    ```
