<?php

namespace Tranquil\Models\Concerns;

use Doctrine\DBAL\Schema\Column;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Trait HasValidation
 *
 * Models that use this will have validation automatically ran on dirty parameters when the model is saved,
 * unless {@see HasValidation::$validateOnSave} is set to <code>false</code>.
 *
 * This will automatically determine validation rules according to the Model's table schema.
 * You can also add/override validation with the {@see HasValidation::$validationRules} property
 * or by overriding the {@see HasValidation::getValidationRules()} method.
 * Same goes for the corresponding validation messages.
 */
trait HasValidation {

	use HasColumnSchema;

	/**
	 * An array of validation rules.
	 * @see HasValidation::getValidationRules()
	 */
	public array $validationRules = [];

	/**
	 * An array of validation messages.
	 * @see HasValidation::getValidationMessages()
	 */
	public array $validationMessages = [];

	/**
	 * A flag for bypassing automatic validation when saving a Model.
	 * @see HasValidation::bootHasValidation()
	 */
	public bool $validateOnSave = true;

	protected static function bootHasValidation() {
		static::saving(function(Model $model) {
			if($model->validateOnSave) {
				$model->validate();
			}
		});
	}

	public function getValidationRules(): array {
		return $this->validationRules;
	}

	public function getValidationMessages(): array {
		return $this->validationMessages;
	}

	/**
	 * Use this to validate a Model according to the attributes you supply or the dirty attributes on the Model.
	 * This will automatically run when a Model is saved unless <code>$validateOnSave</code> is set to <code>false</code>
	 * {@see self::$validateOnSave}
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public function validate( array $attributes = [] ) {
		$this->getValidator( $attributes ?? $this->getDefaultValidationAttributes() )->validate();
	}

	public static function getAllValidationRules(): array {
		return static::getValidationRulesForAttributes(
			static::getColumns()
				  ->except([(new static())->getKeyName(), 'id', 'slug', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by'])
				  ->toArray()
		);
	}

	public static function getValidationRulesForAttributes($attributes = []): array {
		$defaultRules = (new static())->getValidationRules() ?? [];
		$schemaRules = static::getSchemaColumnRules()
							 ->filter( function( $rule, $column ) use ( $attributes ) {
								 return array_key_exists( $column, $attributes );
							 } )
							 ->map( function( $rule, $column ) use ( $defaultRules ) {
								 if( array_key_exists( $column, $defaultRules ) ) {
									 $defaultRule = is_string( $defaultRules[ $column ] )
										 ? explode( '|', $defaultRules[ $column ] )
										 : (is_array( $defaultRules[ $column ] ) ? $defaultRules[ $column ] : [$defaultRules[ $column ]]);
									 $rule = collect( array_merge( $defaultRule, explode('|', $rule) ) )->unique();
								 }

								 return $rule;
							 } )
							 ->toArray();

		return collect( array_merge( $defaultRules, $schemaRules ) )
			->filter( function( $rule, $column ) use ( $attributes ) {
				return array_key_exists( $column, $attributes );
			} )
			->toArray();
	}

	public static function getRequiredColumns(): Collection {
		$defaultRules = collect((new static())->getValidationRules() ?? [])->mapWithKeys(function($rule, $column) {
			return [$column => is_array($rule) && collect($rule)->every(fn($item) => is_string($item)) ? implode('|', $rule) : $rule];
		});

		return static::getSchemaColumnRules()->merge($defaultRules)
					 ->filter( function( $rules ) {
						 if(is_string($rules)) {
							 return Str::contains($rules, 'required');
						 }
						 if(is_array($rules)) {
							 return collect($rules)->contains('required');
						 }
						 return true;
					 } )
					 ->keys();
	}

	public static function getSchemaColumnRules(): Collection {
		return Cache::remember( (new static())->getTable().'_schema_column_rules', 180, function() {
			return collect( static::getColumns() )
				->except( [(new static())->getKeyName(), 'id', 'slug', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by'] )
				->map( function( Column $schema, $column ) {
					$required = $schema->getNotnull() ? ($schema->getDefault() === null ? 'required' : 'filled') : false;
					$nullable = !$required ? 'nullable' : false;
					switch( $schema->getType()->getName() ) {
						case 'integer':
							$rule = 'integer';
							break;
						case 'float':
						case 'double':
						case 'decimal':
							$rule = 'numeric';
							break;
						case 'boolean':
							$rule = 'boolean';
							break;
						default:
							$rule = false;
					}
					return !$required && !$rule ? false : implode( '|', array_filter( [$required, $nullable, $rule] ) );
				} )
				->filter();
		} );
	}

	public function getValidator( array $attributes = [] ): \Illuminate\Contracts\Validation\Validator {
		$attributes = count( $attributes ) ? $attributes : $this->getDefaultValidationAttributes();

		return Validator::make( $attributes, $this->getValidationRulesForAttributes( $attributes ), $this->getValidationMessages() );
	}

	public function getDefaultValidationAttributes(): array {
		$modifiedAttributes = $this->getDirty();
		return $this->exists
			? $modifiedAttributes
			: $this->getRequiredColumns()
				   ->mapWithKeys( function( $column ) use( $modifiedAttributes )  {
					   return [$column => $modifiedAttributes[$column] ?? null];
				   } )
				   ->toArray();
	}
}
