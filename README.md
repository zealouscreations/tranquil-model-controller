# Tranquil Models and Controllers

This package contains base Laravel Eloquent models and controllers that contain all the functionality you'll need for CRUD operations. Making Laravel development a more "Tranquil" experience.

**Compatible with Laravel 10, 11, and 12**

## Install

### Require it with composer

```shell
composer require zealous-creations/tranquil-model-controller
```

> NOTE: If you have installed `inertiajs/inertia-laravel` lower than `v0.6` or `doctrine/dbal` lower than `v3.0`
> then you may need to remove them before requiring `zealous-creations/tranquil-model-controller`

   ```shell
   composer remove inertiajs/inertia-laravel
   composer remove doctrine/dbal
   composer require zealous-creations/tranquil-model-controller
   ```

### Publish migrations

There are 2 migrations in this package for creating a `users` table and an `attachments` table

If you want to modify these migrations be for running them, then run this artisan command:
```shell
php artisan vendor:publish --tag=tranquil-model-migrations
```

## Usage

### Tranquil Controller

The Tranquil Controller takes care of all the methods of a standard Laravel controller: `index`, `create`, `show`, `edit`, `store`, and `destroy`

For any of your model controllers all you have to do is extend `TranquilController`

```php
class CarController extends TranquilController {}
```

Then all you need to do is add the resource routes for the model.

```php
// routes/web.php

Route::resource('cars', CarController::class);
```

Now all of the endpoints for the resource routes will automatically work - without having to add any methods to your controller.

```
GET|HEAD  cars .................. cars.index › CarController@index
POST      cars .................. cars.store › CarController@store
GET|HEAD  cars/create ......... cars.create › CarController@create
GET|HEAD  cars/{car} .............. cars.show › CarController@show
PUT|PATCH cars/{car} .......... cars.update › CarController@update
DELETE    cars/{car} ........ cars.destroy › CarController@destroy
GET|HEAD  cars/{car}/edit ......... cars.edit › CarController@edit
```

#### Show Endpoint Example

`GET /cars/1`
```json
{
  "success": true,
  "message": "",
  "car": {
    "id": 1,
    "make": "Audi",
    "model": "A3",
    "year": 2016
  }
}
```

#### Update Endpoint Example

`PATCH /cars/1 PAYLOAD {"year": 2024}`

This will update `year` column of the `cars` record that has the id of `1` - As long as the `year` is included in the `$fillable` model parameter.

Or if you have the model extend `TranquilModel`

```php
class Car extends TranquilModel {
    //...
}
```

#### Store Endpoint Example

`POST /cars PAYLOAD {"make": "Tesla", "model": "Model S", year": 2024}`

This will create a new record in the `cars` table. You can have automatic input validation if the model extends `TranquilModel` or uses the `HasValidation` trait.

```php
class Car extends Model {
    use HasValidation;
    //...
}
```

#### List Endpoint

There is also a `list` route endpoint you can add for fetching a list of records for the model

```php
// routes/web.php

Route::post('cars/list', [CarController::class, 'list'])->name('cars.list');
```

#### Example

`POST /cars/list PAYLOAD {"where": {"make": "Buick"}}`
```json
{
  "success": true,
  "message": "",
  "total": 4,
  "records": [
    {
      "id": 108,
      "make": "Buick",
      "model": "Enclave",
      "year": 2016
    },
    {
      "id": 109,
      "make": "Buick",
      "model": "Encore GX",
      "year": 2016
    },
    {
      "id": 110,
      "make": "Buick",
      "model": "Envision",
      "year": 2016
    },
    {
      "id": 111,
      "make": "Buick",
      "model": "Envista",
      "year": 2016
    }
  ]
}
```

### Tranquil Inertia Controller

You can return Inertia responses for all the standard controller methods by extending the `TranquilInertiaController`

```php
class CarController extends TranquilInertiaController {}
```

Now all of the endpoints will return an `Inertia` response to the corresponding component path.

```
/cars .............. resources/js/Pages/Cars/Index
/cars/create ....... resources/js/Pages/Cars/CreateEdit
/cars/{car} ........ resources/js/Pages/Cars/Show
/cars/{car}/edit ... resources/js/Pages/Cars/CreateEdit
```

### User Model

This package also comes with a `TranquilUser` model that is for the authenticated user.

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