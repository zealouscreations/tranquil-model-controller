# Traits

This package provides several traits to augment your Eloquent models and controllers.

HasValidation (Models)
- Automatically validates model attributes on save (saving event) using rules derived from the database schema and any explicit rules you provide.
- Disable auto-validation by setting `$validateOnSave = false` on a model instance.
- Extend/override rules via `$validationRules` property or by overriding `getValidationRules()`.
- Default messages via `$validationMessages` or `getValidationMessages()`.
- Methods:
  - validate(array $attributes = []): Validates provided attributes or the model's dirty attributes.
  - getAllValidationRules(): Returns rules for all columns (excluding id, timestamps, etc.).
  - getValidationRulesForAttributes($attributes): Merges schema-derived rules with your custom rules.
  - getRequiredColumns(): Returns a collection of required column names (computed from rules including required/sometimes).
  - getDefaultValidationAttributes(): Provide default values that are merged during validation.

HasColumnSchema (Models)
- Used by HasValidation to introspect the table schema via Schema::getColumns() (Doctrine DBAL required by Laravel).
- Methods:
  - getColumns(): Collection keyed by column name containing schema info (cached for 3 minutes).
  - getColumnSchema(string $column): Returns schema for a single column.
  - getColumnType(string $column): Returns type_name.

HasPolicy (Models)
- Adds helpers to include/append policy permissions for models and relations and to filter queries by allowed actions for the current user.
- Methods/scopes:
  - getPolicyAttribute(): Array of allowed abilities for the current user on the model.
  - appendPolicies(array $relations): Appends policy data to the model and optionally its relations.
  - scopeAppendPolicies(array $relations): Append policy data to query results.
  - scopeGetWhereCan(string|array $action): Returns a collection of models the user can act on.
  - scopeWhereCan(string|array $action): Filters the query to models the user can act on.

HasAttachments (Models)
- Adds a morphMany attachments() relation to an Attachment model for simple file associations.

InertiaResponses (Controllers)
- For controllers implementing ResourceResponsesInterface, this trait provides Inertia responses for index, create, show, edit.
- You can override component paths via public properties: $componentPathPrefix, $indexPath, $createPath, $showPath, $editPath.
- getComponentPathPrefix() defaults to plural CamelCase of the model class basename (e.g., Car => Cars).
