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

	private Collection $defaultRules;
	private Collection $confirmedColumns;

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
		$this->getValidator( $attributes )->validate();
	}

	public function getAllValidationRules(): array {
		return $this->getValidationRulesForAttributes(
			static::getColumns()
				  ->except([(new static())->getKeyName(), 'id', 'slug', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by'])
				  ->toArray()
		);
	}

	public function getValidationRulesForAttributes( $attributes = [] ): array {
		$defaultRules = $this->getValidationRules() ?? [];
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

	public function getRequiredColumns(): Collection {
		return static::getSchemaColumnRules()->merge( $this->getDefaultRules() )->merge( $this->getConfirmedColumns() )
					 ->filter( function( $rules ) {
						 if( is_string( $rules ) ) {
							 return Str::contains( $rules, 'required' ) && !Str::contains( $rules, 'sometimes' );
						 }
						 if( is_array( $rules ) ) {
							 return collect( $rules )->contains( fn( $rule ) => is_string( $rule ) && Str::contains( $rule, 'required' ) ) &&
									!collect( $rules )->contains( fn( $rule ) => is_string( $rule ) && Str::contains( $rule, 'sometimes' ) );
						 }
						 return true;
					 } )
					 ->keys();
	}

	public function getDefaultRules(): Collection {
		if( !isset( $this->defaultRules ) ) {
			$this->defaultRules = collect( $this->getValidationRules() ?? [] )->mapWithKeys( function( $rule, $column ) {
				return [$column => is_array( $rule ) && collect( $rule )->every( fn( $item ) => is_string( $item ) ) ? implode( '|', $rule ) : $rule,];
			} );
		}

		return $this->defaultRules;
	}

	public function getConfirmedColumns(): Collection {
		if( !isset( $this->confirmedColumns ) ) {
			$this->confirmedColumns = $this->getDefaultRules()->mapWithKeys( function( $rules, $column ) {
				$confirmed = false;
				if( is_string( $rules ) ) {
					$confirmed = Str::contains( $rules, 'confirmed' ) && !Str::contains( $rules, 'sometimes' );
				}
				if( is_array( $rules ) ) {
					$confirmed = collect( $rules )->contains( fn( $rule ) => is_string( $rule ) && Str::contains( $rule, 'confirmed' ) ) &&
								 !collect( $rules )->contains( fn( $rule ) => is_string( $rule ) && Str::contains( $rule, 'sometimes' ) );
				}
				return $confirmed ? [$column.'_confirmation' => 'required'] : [];
			} )->filter();
		}

		return $this->confirmedColumns;
	}

	public static function getSchemaColumnRules(): Collection {
		return Cache::remember( (new static())->getTable().'_schema_column_rules', 180, function() {
			return collect( static::getColumns() )
				->except( [(new static())->getKeyName(), 'id', 'slug', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by', 'createdUserID', 'updatedUserID', 'deletedUserID'] )
				->map( function( $schema ) {
					$required = $schema->nullable ? false : ($schema->default === null ? 'required' : 'filled');
					$nullable = !$required ? 'nullable' : false;
					$schema->type_name = $schema->type == 'tinyint(1)' ? 'boolean' : $schema->type_name;
					switch( $schema->type_name ) {
						case 'int':
						case 'tinyint':
						case 'smallint':
						case 'mediumint':
						case 'bigint':
						case 'integer':
							$rule = 'integer';
							break;
						case 'float':
						case 'double':
						case 'decimal':
						case 'number':
						case 'numeric':
							$rule = 'numeric';
							break;
						case 'bit':
						case 'boolean':
							$rule = 'boolean';
							break;
						default:
							$rule = 'string';
					}
					return implode( '|', array_filter( [$required, $nullable, $rule] ) );
				} )
				->filter();
		} );
	}

	public function getValidator( array $attributes = [] ): \Illuminate\Contracts\Validation\Validator {
		$attributes = count( $attributes ) ? $attributes : $this->getDefaultValidationAttributes();

		return Validator::make( $attributes, $this->getValidationRulesForAttributes( $attributes ), $this->getValidationMessages() );
	}

	public function getDefaultValidationAttributes(): array {
		$filledColumns = $this->getSchemaColumnRules()->filter(fn($rule) => Str::contains($rule, 'filled'));
		foreach($this->getDirty() as $column => $value) {
			if($filledColumns->has($column) && $value === null) {
				unset($this->attributes[$column]);
			}
		}
		$modifiedAttributes = $this->getDirty();
		$confirmationInputs = $this->getConfirmedColumns()->mapWithKeys( function( $item, $column ) {
			$regularColumn = str_replace( '_confirmation', '', $column );
			return [$column => request()->request->get( $column ) ?? (!$this->isDirty( $regularColumn ) ? $this->$regularColumn : null)];
		} )->toArray();
		$modifiedAttributes = array_merge( $confirmationInputs, $modifiedAttributes );

		return $this->getRequiredColumns()
					->mapWithKeys( function( $column ) use ( $modifiedAttributes ) {
						$value = array_key_exists( $column, $modifiedAttributes )
							? $modifiedAttributes[ $column ]
                            : ($this->exists ? ($this->getAttributes()[$column] ?? null) : null);

						return [$column => $value];
					} )
					->merge( $modifiedAttributes )
					->toArray();
	}
}
