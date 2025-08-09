# Installation

Tranquil Model Controller is distributed as a Laravel package.

Requirements
- PHP compatible with Laravel 10–12
- Laravel 10, 11, or 12

Install
1. Require the package via Composer:
   
   composer require zealous-creations/tranquil-model-controller

2. Publish migrations (optional, only if you want to customize them before running):
   
   php artisan vendor:publish --tag=tranquil-model-migrations

3. Run your migrations:
   
   php artisan migrate

Upgrading
- Check the changelog.md for noteworthy changes.
- Re-run migrations only if new migrations are added in a version update.
