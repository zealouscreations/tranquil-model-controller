# Models

TranquilModel extends Eloquent Model and provides sensible defaults and helpers for appends and validation.

Key features
- Guarded: id, created_at, updated_at, deleted_at
- timestamps enabled
- snakeAttributes disabled (public static $snakeAttributes = false)
- Appends scoping helpers to control appended accessors on a per-query basis
- Validation via HasValidation trait

Appends Control

```php
$model->toArray(); // includes appends

// Temporarily override appends for the current query scope
Car::query()->overrideAppends(['fullName'])->get();

// Add appends for the current query scope
Car::query()->addAppends(['fullName', 'summary'])->get();

// Remove appends for the current query scope
Car::query()->withoutAppends()->get();
```

Validation
TranquilModel uses HasValidation. Override these on your model:

```php
public function getValidationRules(): array
{
    return [
        'make' => ['required', 'string'],
        'model' => ['required', 'string'],
        'year' => ['required', 'integer', 'min:1900'],
    ];
}

public function getDefaultValidationAttributes(): array
{
    return [
        'make' => '',
        'model' => '',
        'year' => null,
    ];
}
```

TranquilUser
An authenticatable user model offering role utilities (hasRole, addRole/removeRole, hasAllRoles, scopes whereHasRole/whereHasAllRoles). It also defines default validation rules and a computed name attribute. See source: src/Models/TranquilUser.php.
