<?php

namespace Tranquil\Models\Concerns;

use Doctrine\DBAL\Schema\Column;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

trait HasValidation {

	use HasColumnSchema;

	public array $validationRules = [];
	public array $validationMessages = [];
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

	public static function getAllValidationRules(): array {
		return static::getValidationRulesForAttributes(
			static::getColumns()
			      ->except(['id', 'slug', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by'])
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
			return [$column => is_array($rule) ? implode('|', $rule) : (is_string($rule) ? $rule : null)];
		});

		return static::getSchemaColumnRules()->merge($defaultRules)
					 ->filter( function( $rules ) {
						 return Str::contains( $rules, 'required' ) && !Str::contains( $rules, 'sometimes' );
					 } )
					 ->keys();
	}

	public static function getSchemaColumnRules(): Collection {
		return Cache::remember( (new static())->getTable().'_schema_column_rules', 180, function() {
			return collect( static::getColumns() )
				->except( ['id', 'slug', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by'] )
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

	/**
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public function validate( array $attributes = [] ) {
		$this->getValidator( $attributes ?? $this->getDefaultValidationAttributes() )->validate();
	}

	public function getValidator( array $attributes = [] ): \Illuminate\Contracts\Validation\Validator {
		$attributes = count( $attributes ) ? $attributes : $this->getDefaultValidationAttributes();

		return Validator::make( $attributes, $this->getValidationRulesForAttributes( $attributes ), $this->validationMessages );
	}

	public function getDefaultValidationAttributes(): array {
		$modifiedAttributes = $this->getDirty();
		return $this->exists ? $modifiedAttributes : array_merge(
			$this->getRequiredColumns()
				 ->mapWithKeys( function( $column ) {
					 return [$column => null];
				 } )
				 ->toArray(),
			$modifiedAttributes );
	}
}
