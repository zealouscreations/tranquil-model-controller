# Controllers

This package centers around ModelController, which powers TranquilController and TranquilInertiaController. It implements the full RESTful resource flow and adds helpful utilities for relations, files, policies, and list filtering.

- ModelController: Base class containing all logic
- TranquilController: Extends ModelController, mixes in TranquilResourceMethods to provide index, create, show, edit, store, update, destroy
- TranquilInertiaController: Same as TranquilController but provides Inertia-based responses via InertiaResponses trait

Usage

```php
use Tranquil\Controllers\TranquilController;

class CarController extends TranquilController {
    protected string $modelClass = Car::class; // define your model class
}
```

Resource Methods
- index(): returns the list view / response
- create(): returns the create view / response
- show(Model $model): returns the show view / response
- edit(Model $model): returns the edit view / response
- store(Request $request): persists a new record (validates if model uses HasValidation)
- update(Request $request, Model $model): updates an existing record
- destroy(Request $request, Model $model): deletes the record

Additional Endpoints
- list(Request $request): returns a JSON payload of records when you need a lightweight list endpoint for UI selects and AJAX filtering.

Validation
- If your model extends TranquilModel or uses the HasValidation trait, inputs are validated automatically using rules returned by getValidationRules() and default attributes from getDefaultValidationAttributes().

Relation Syncing
ModelController understands nested payloads for the following relations, both on create and update:
- belongsTo
- hasOne, hasMany
- belongsToMany (with pivot data)
- morphOne, morphMany, morphToMany

Payload shapes (examples)
- belongsTo: Provide `{ relation: { id: 123 } }` to associate, or `{ relation: null }` to disassociate.
- hasOne: Provide `{ relation: { id: 456, ...attributes } }` to create/update. Use `{ relation: null }` to remove association.
- hasMany: Provide `{ relation: [{ id: 1, ...}, { id: null, ...new }, ...] }`. Include an `_delete: true` flag on a child to delete it when `$deletableAsHasMany` is true on the child model.
- belongsToMany/morphToMany: Provide `{ relation: [{ id: 5, pivot: { key: value }}, ...] }`. Items without `id` are created if the child is creatable.

Filtering Lists (list endpoint)
The list endpoint can process filters and search operators. Common patterns (see tests/Unit/ModelControllerTest.php for exhaustive cases):
- Simple contains search across columns: `?search=term&columns=name,description`
- Starts/Ends/Contains operators
- IN and BETWEEN operators: pass `filters` payload with operator and values
- Relation-based filters with `relation.column` notation
- Date strings and boolean values are handled appropriately

Policies
ModelController integrates with policies via HasPolicy trait on your models and the controller’s checkModelPolicy() method. Ensure your user model policies allow/deny actions and the controller will enforce them during operations.

Attachments and Files
If your model uses HasAttachments, ModelController’s saveFiles() and saveAttachments() will persist uploaded files and create Attachment records accordingly.

Responses
- JSON for API calls via apiResponse()
- For Inertia apps, use TranquilInertiaController which provides Inertia::render responses for index, create, show, and edit.

Advanced Response Parameters
The controller constructs parameter arrays for each response type and can load relations/appends dynamically. See methods like getLoadRelations(), getLoadAppends(), getCreateEditParametersWithModel().
