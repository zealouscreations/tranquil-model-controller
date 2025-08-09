# Inertia Integration

If you're building an Inertia.js app, use TranquilInertiaController, which mixes in InertiaResponses to render Inertia pages for each resource response.

Usage

```php
use Tranquil\Controllers\TranquilInertiaController;

class CarController extends TranquilInertiaController {
    protected string $modelClass = Car::class;
}
```

Component Paths
- Defaults are derived from your model class name using plural CamelCase. For a Car model, defaults are:
  - Index: Cars/Index
  - Create: Cars/CreateEdit
  - Show: Cars/Show
  - Edit: Cars/CreateEdit

Override paths via public properties on the controller:

```php
public string $componentPathPrefix = 'Admin/Cars';
public string $indexPath = 'Admin/Cars/Index';
public string $createPath = 'Admin/Cars/CreateEdit';
public string $showPath = 'Admin/Cars/Show';
public string $editPath = 'Admin/Cars/CreateEdit';
```

Response Parameters
- The base controller builds parameter arrays for each response type. You can customize them by overriding methods like getIndexResponseParameters(), getCreateResponseParameters(), etc.

Policies
- All Tranquil controllers enforce policies. Ensure your policy methods (viewAny, view, create, update, delete) are implemented for your model.
