# Quick Start

This package provides a ready-to-use base controller and model to implement standard CRUD with minimal boilerplate.

1) Create a model extending TranquilModel (or use HasValidation on your own Eloquent model):

```php
use Tranquil\Models\TranquilModel;

class Car extends TranquilModel {
    protected $table = 'cars';
    protected $fillable = ['make', 'model', 'year'];
}
```

2) Create a controller extending TranquilController:

```php
use Tranquil\Controllers\TranquilController;

class CarController extends TranquilController {}
```

3) Add resource routes:

```php
use Illuminate\Support\Facades\Route;

Route::resource('cars', CarController::class);
```

4) Optional: Add a list endpoint for easy filtered lists

```php
Route::get('cars-list', [CarController::class, 'list']);
```

That’s it. Endpoints work out-of-the-box:
- GET /cars (index)
- GET /cars/create (create)
- POST /cars (store)
- GET /cars/{car} (show)
- GET /cars/{car}/edit (edit)
- PATCH/PUT /cars/{car} (update)
- DELETE /cars/{car} (destroy)

Validation
- TranquilModel includes HasValidation. Override getValidationRules() and getDefaultValidationAttributes() as needed.

Relations
- ModelController handles syncing belongsTo, hasOne, hasMany, belongsToMany, morphOne, morphMany, morphToMany when you pass nested arrays in your request payload. See controllers.md for the expected shapes.
