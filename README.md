# Tranquil Models and Controllers

This package contains base Laravel Eloquent models and controllers that contain all the functionality you'll need for CRUD operations. Making Laravel development a more "Tranquil" experience.

**Requires Laravel 10**

## Install

Add this to the `repositories` array in the `composer.json` file:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "git@bitbucket.org:zealous-creations/tranquil-model-controller.git"
    }
  ]
}
```

Then require it with composer

```shell
composer require zealous-creations/tranquil-model-controller
```

There are 2 migrations in this package for creating a `users` table and an `attachments` table

If you want to modify these migrations be for running them, then run this artisan command:
```shell
php artisan vendor:publish --tag=tranquil-model-migrations
```

## Usage

### User Model

This comes with a `TranquilUser` model that is for the authenticated user.

You can extend this model to modify it:

```php
class User extends \Tranquil\Models\TranquilUser {
    
    public const roleOptions = [
		[
			'handle'      => 'super',
			'name'        => 'Super User',
			'description' => 'Has full access',
		],
		[
			'handle'      => 'leader',
			'name'        => 'Leader',
			'description' => 'Has administrator access',
		],
		[
			'handle'      => 'staff',
			'name'        => 'Staff',
			'description' => 'Has basic access',
		],
	];
	
	//...
}
```