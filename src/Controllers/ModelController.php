<?php

namespace Tranquil\Controllers;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Tranquil\Models\Attachment;
use Tranquil\Models\Concerns\HasAttachments;
use Tranquil\Models\Concerns\HasColumnSchema;
use Tranquil\Models\Concerns\HasValidation;
use Tranquil\Models\TranquilUser as User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class ModelController extends Controller implements ResourceResponsesInterface {

	public string $modelClass;

	public Builder|Relation $modelQuery;

	public array $responseTypeParameters = [];
	public array $createEditParameters = [];

	public function __construct() {
		if( !isset( $this->modelClass ) ) {
			$this->modelClass = str_replace( ['App\Http\Controllers', 'Controller'], ['App\Models', ''], get_class( $this ) );
		}
	}

	public function getResponse( $type, $model = null, $parameters = [] ) {
		$policyType = match ($type) {
			'index' => 'viewAny',
			'create' => 'create',
			'show' => 'view',
			'edit' => 'update',
			default => null,
		};
		$this->checkModelPolicy( $model ?? new $this->modelClass(), $policyType );


		return $this->{$type.'Response'}( $model ?? $this->modelClass, $this->getResponseParameters( $type, $model, $parameters ) );
	}

	public function getResponseParameters( $type, $model, $parameters ): array {
		$parameters = array_merge(
			$parameters,
			$this->getResponseTypeParameters( $type ),
			$this->getModelPolicyParameters( $model ?? new $this->modelClass() )
		);
		if( in_array( $type, ['create', 'edit'] ) ) {
			$parameters = array_merge( $parameters, $this->getCreateEditParametersWithModel( $model ) );
		}

		return $parameters;
	}

	/**
	 * Validates and saves a model
	 *
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public function save( Request $request, mixed $model ): bool|JsonResponse|Responsable|\Illuminate\Http\RedirectResponse {
		/** @var $model Model */
		if( $model->exists ) {
			$this->checkModelPolicy( $model, 'update' );
		}
		if ( in_array( HasValidation::class, class_uses_recursive( $model ) ) && !$model->validateOnSave ) {
			$model->validate();
		}
		$this->saveRelations( $request, $model );
		$model->save();
		$this->saveAttachments( $request, $model );

		session()->flash('message', $model->wasRecentlyCreated ? 'Created' : 'Saved');
		return $model->wasRecentlyCreated
			? $this->stored( $model )
			: $this->saved( $model );
	}

	public function stored( Model $model ): bool|JsonResponse|Responsable|\Illuminate\Http\RedirectResponse {
		return $this->saved( $model );
	}

	public function saved( Model $model ): bool|JsonResponse|Responsable|\Illuminate\Http\RedirectResponse {
		$request = request();
		if( !$request->header( 'X-Inertia' ) && ($request->expectsJson() || !method_exists($this->modelClass, 'show')) ) {
			return $this->apiResponse( true, [ Str::camel(class_basename( $model )) => $model->fresh() ] );
		}
		$routeName = $this->getRouteNameForAction( get_class($model), 'show');
		$redirect = $this->redirectResponse( request(), $model->fresh() ) ?: (
			Route::has( $routeName )
				? redirect()->route( $routeName, [Str::camel( class_basename( $model ) ) => $model->fresh()] )
				: false
		);

		return $redirect ?: (
			method_exists( $this, 'show' )
				? $this->show( $model->fresh() )
				: $this->showResponse( $model->fresh() )
			);
	}

	/**
	 * Saves related models that are included in the request
	 */
	public function saveRelations( Request $request, Model $model ) {
		$relationsToCreate = [];
		$relationsToUpdateOrCreate = [];
		$relationsToSync = [];
		$createdAssociatedRelation = false;
		foreach( $request->input() as $relatedColumn => $input ) {
			if( is_array( $input ) && method_exists( $model, $relatedColumn ) && is_a( $model->$relatedColumn(), Relation::class ) ) {
				$hasOne = is_a( $model->$relatedColumn(), HasOne::class ) || is_a( $model->$relatedColumn(), MorphTo::class ) || is_a( $model->$relatedColumn(), MorphOne::class );
				$belongsTo = is_a( $model->$relatedColumn(), BelongsTo::class );
				$belongsToMany = is_a( $model->$relatedColumn(), BelongsToMany::class ) || is_a( $model->$relatedColumn(), MorphToMany::class );
				$hasMany = is_a( $model->$relatedColumn(), HasMany::class ) || is_a( $model->$relatedColumn(), MorphMany::class );
				if( $hasOne || $belongsTo ) {
					if( isset( $model->$relatedColumn ) ) {
						$model->$relatedColumn->fill( $input );
						$model->$relatedColumn->save();
					} else {
						if( $hasOne ) {
							$relationsToCreate[] = compact('relatedColumn', 'input');
						} else {
							if( isset( $input['id'] ) ) {
								$relatedModel = $model->$relatedColumn()->getModel()->where( 'id', $input['id'] )->first();
								$relatedModel->fill( $input );
								$relatedModel->save();
							} else {
								$relatedModel = $model->$relatedColumn()->create( $input );
							}
							if( $relatedModel ) {
								$model->$relatedColumn()->associate( $relatedModel );
								$createdAssociatedRelation = true;
							}
						}
					}
				} else if( $belongsToMany || $hasMany ) {
					if( $belongsToMany ) {
						if( $input == [] ) {
							$relationsToSync[] = compact( 'relatedColumn', 'input' );
						} else {
							$inputCollection = collect( $input );
							if( $inputCollection->pluck( 'id' )->filter()->count() ) {
								$pivotField = $model->$relatedColumn()->getPivotAccessor();
								$input = $inputCollection->mapWithKeys( function( $item, $key ) use ( $pivotField ) {
									$itemArray = (array) $item;
									$id = $itemArray['id'] ?? (is_numeric( $item ) ? $item : $key);
									$pivotAttributes = isset($itemArray[ $pivotField ]) ? Arr::except($itemArray[ $pivotField ], ['errors']) : null;
									return [$id => $pivotAttributes ?? $id];
								} )->toArray();
							}
							$relationsToSync[] = compact( 'relatedColumn', 'input' );
						}
					} else {
						$foreignKey = $hasMany ? $model->$relatedColumn()->getForeignKeyName() : null;
						foreach( $input as $relationInput ) {
							$relationsToUpdateOrCreate[] = compact( 'relatedColumn', 'foreignKey', 'relationInput' );
						}
					}
				}
			}
		}
		if( $createdAssociatedRelation || (!$model->exists && count( $relationsToCreate ) + count( $relationsToUpdateOrCreate ) + count( $relationsToSync ) > 0) ) {
			$model->save();
		}
		foreach($relationsToCreate as $relation) {
			$relatedColumn = $relation['relatedColumn'];
			$foreignKey = $model->$relatedColumn()->getForeignKeyName();
			$relation['input'][ $foreignKey ] = $model->getKey();
			$model->$relatedColumn()->create( $relation['input'] );
		}
		foreach($relationsToUpdateOrCreate as $relation) {
			$relatedColumn = $relation['relatedColumn'];
			$input = (array) $relation['relationInput'];
			if( isset( $relation['foreignKey'] ) ) {
				$input[ $relation['foreignKey'] ] = $model->getKey();
			}
			$model->$relatedColumn()->updateOrCreate( ['id' => $input['id'] ?? null], $input );
		}
		foreach($relationsToSync as $relation) {
			$relatedColumn = $relation['relatedColumn'];
			$model->$relatedColumn()->sync($relation['input']);
		}
	}

	/**
	 * Uploads or deletes attachments that are included in the request, if the model has attachments
	 */
	public function saveAttachments( Request $request, Model $model ) {
		if( in_array( HasAttachments::class, class_uses_recursive( $model ) ) ) {
			if( !$model->wasRecentlyCreated && $request->has( 'attachments' ) ) {
				$attachmentsToDelete = $model->attachments()
											 ->whereNotIn( 'id', collect( $request->attachments )->pluck( 'id' ) )
											 ->get();
				foreach( $attachmentsToDelete as $attachment ) {
					$attachment->delete();
				}
			}
			if( !empty( $request->file( 'files' ) ) ) {
				foreach( $request->file( 'files' ) as $file ) {
					$attachment = new Attachment( $request->attachmentParameters ?? [] );
					$attachment->storeFile( $file['file'] );
					$model->attachments()->save( $attachment );
				}
			}
		}
	}

	/**
	 * Use $request->permanent = true to permanently remove from the database
	 * Use $request->redirectRoute to redirect to a different route - defaults to model's index
	 * Use $request->redirectParameters to add parameters to the redirect route - e.g. ['model' => 1]
	 */
	public function remove( Request $request, mixed $model ): JsonResponse|Responsable|\Illuminate\Http\RedirectResponse {
		/** @var $model Model */

		if ( $request->permanent ) {
			$this->checkModelPolicy( $model, 'force-delete' );
			$model->forceDelete();
		} else {
			$this->checkModelPolicy( $model, 'delete' );
			$model->delete();
		}

		if( !$request->header( 'X-Inertia' ) && ($request->expectsJson() || !method_exists($this->modelClass, 'index')) ) {
			return $this->redirectResponse( $request )
				?: $this->apiResponse( true );
		}
		session()->flash('message', 'Deleted');
		return $this->redirectResponse( $request )
			?: $this->removed( $request );
	}

	public function removed( Request $request ): Responsable|\Illuminate\Http\RedirectResponse {
		$routeName = $this->getRouteNameForAction( $this->modelClass, 'index');

		return Route::has( $routeName )
			? redirect()->route( $routeName )
			: ( method_exists( $this, 'index' )
				? $this->index( $request )
				: $this->indexResponse( $request )
			);
	}

	/**
	 * Retrieve data for a single model
	 *
	 * Optional request parameter:
	 * $request->relations -- array -- for eager loading related models
	 */
	public static function getModel( Request $request, Model $model ): JsonResponse {
		return static::apiResponse( true, [ Str::camel( Str::singular( $model->getTable() ) ) => $model->load( $request->relations ?? [] ) ] );
	}

	public function getModelQuery( Request $request ): Relation|Builder {
		if ( isset($this->modelQuery) ) {
			return $this->modelQuery->with( $request->relations ?? [] );
		}

		return $this->modelClass::with( $request->relations ?? [] );
	}

	/**
	 * Retrieve a list of records for the model
	 *
	 * Optional request parameters:
	 * $request->relations -- array -- for eager loading related models
	 * $request->select -- string|array -- for selecting specific columns
	 * $request->orderBy -- string -- for ordering list by column(s)
	 * $request->offset -- int -- the index at which to start the list from
	 * $request->limit -- int -- the maximum number of records to return
	 */
	public function list( Request $request ): JsonResponse {
		return $this->apiResponse( true, $this->getRecords( $request ) );
	}

	public function getRecords( Request $request ): array {
		$query = $this->getModelQuery( $request );
		if ( $request->select ) {
			$query->select( $request->select );
		}
		if ( $request->search ) {
			$this->addQueryFilters( $query, $request->search );
		}
		if ( $request->orderBy ) {
			$query->orderByRaw( $request->orderBy );
		}
		if($request->where){
			$query->where($request->where);
		}
		$records = $query->get();
		$total = $records->count();
		$records = $this->sortRecords( $records, $request );
		$records = $records
			->slice( $request['offset'] ?: 0 )
			->take( $request['limit'] ?: 200 )
			->values()
			->all();
		return compact( 'total', 'records' );
	}

	public function sortRecords( Collection $records, Request $request ): Collection {
		return $request->sortBy ? $records->sortBy( $request->sortBy, SORT_REGULAR, $request->descending ) : $records;
	}

	/**
	 * $filters examples:
	 * [
	 *     [
	 *         'logic'         => 'and',        // optional - 'and', 'or' - default is 'and'
	 *         'column'        => 'first_name', // required
	 *         'rawColumn'     => null        , // optional - for using raw sql on the left side of the where clause operator
	 *         'operator'      => '=',          // optional - any valid SQL operator - default is '=' - 'startsWith', 'contains', and 'endsWith' will translate to 'like'
	 *         'value'         => 'John',       // required
	 *         'type'          => null,         // optional - 'date', 'bool', 'boolean', 'raw' - defaults to the table column schema type
	 *         'caseSensitive' => false,        // optional - default is false
	 *     ],
	 *     [
	 *         'logic'    => 'and',             // Multi column search
	 *         'column'   => 'last_name',
	 *         'operator' => '=',
	 *         'value'    => 'Doe',
	 *     ],
	 *     [
	 *         'logic'    => 'and',
	 *         'column'   => 'user.email',      // One level relation search with dot notation (relation.column)
	 *         'operator' => '=',
	 *         'value'    => 'johndoe@example.com',
	 *     ],
	 *     [
	 *         'logic'     => 'or',
	 *         'subSearch' => [                 // Sub group search
	 *             [
	 *                 'column'   => 'job_title',
	 *                 'operator' => '=',
	 *                 'value'    => 'Staff',
	 *             ],
	 *             [
	 *                 'logic'    => 'and',
	 *                 'column'   => 'business_phone',
	 *                 'operator' => 'startsWith',
	 *                 'value'    => '555',
	 *             ],
	 *         ],
	 *     ],
	 * ]
	 */
	public function addQueryFilters( Builder &$query, array $filters ) {
		foreach ( $filters as $filter ) {
			$where = isset( $filter['logic'] ) && strtolower( $filter['logic'] ) == 'or' ? 'orWhere' : 'where';

			if ( isset( $filter['subSearch'] ) ) {
				$query->$where( function ( $query ) use ( $filter ) {
					$this->addQueryFilters( $query, $filter['subSearch'] );
				} );
			} else if ( is_array( $filter['value'] ) || ( isset( $filter['value'] ) && strtolower( $filter['value'] ) != '_all_' ) ) {
				$rawColumn = isset($filter['rawColumn']);
				$column = $rawColumn ? DB::raw($filter['rawColumn']) : $filter['column'];
				$relation = null;
				$lastDotPosition = strrpos( $column, '.' );
				if ( !$rawColumn && $lastDotPosition ) {
					// One level deep relation search 'relationName.column_name'
					$relation = substr( $column, 0, $lastDotPosition );
					$column   = substr( $column, $lastDotPosition + 1 );
				}
				if( strtolower( $filter['operator'] ?? '' ) == 'in' ) {
					$searchValue = $filter['value'];
					$this->populateFilterQueryWhereClause( $query, $where, $relation, function( $query ) use ( $column, $where, $searchValue ) {
						$query->{$where.'In'}( $column, $searchValue );
					} );
				} else {
					$operator = isset( $filter['operator'] ) ? str_replace( [
						'startsWith',
						'contains',
						'endsWith',
					], 'like', $filter['operator'] ) : '=';
					$type = $filter['type'] ?? null;
					if( !$type && !$rawColumn ) {
						/** @var Model $model */
						$model = (new $this->modelClass);
						$model = $relation ? $model->$relation()->getModel() : $model;
						if( in_array( HasColumnSchema::class, class_uses_recursive( $model ) ) ) {
							$type = $model->getColumnType( $column );
						}
					}

					if( $type == 'date' || $type == 'datetime' ) {

						if( $operator == 'between' && is_array( $filter['value'] ) && count( $filter['value'] ) > 1 ) {
							$this->populateFilterQueryWhereClause( $query, $where, $relation, function( $query ) use ( $column, $where, $filter ) {
								$query->$where( function( $query ) use ( $column, $filter ) {
									$query->whereDate( $column, '>=', date( 'Y-m-d', strtotime( $filter['value'][0] ) ) );
									$query->whereDate( $column, '<=', date( 'Y-m-d', strtotime( $filter['value'][1] ) ) );
								} );
							} );
						} else {
							$this->populateFilterQueryWhereClause( $query, $where, $relation, function( $query ) use ( $column, $where, $operator, $filter ) {
								$query->{$where.'Date'}( $column, $operator, date( 'Y-m-d', strtotime( $filter['value'] ) ) );
							} );
						}

					} else {

						$searchValue = $filter['value'];

						switch( $type ) {
							case 'bool':
							case 'boolean':
								if( in_array( $searchValue, ['yes', 'true', '1', 1] ) ) {
									$searchValue = true;
								} else if( in_array( $searchValue, ['no', 'false', '0', 0] ) ) {
									$searchValue = false;
								}
								break;
							case 'raw':
								$searchValue = DB::raw( $searchValue );
								break;
							default:
								if( $operator == 'like' ) {
									$searchValue = (in_array( $filter['operator'], [
											'endsWith',
											'contains',
											'like',
										] ) ? '%' : '')
												   .$searchValue.
												   (in_array( $filter['operator'], [
													   'startsWith',
													   'contains',
													   'like',
												   ] ) ? '%' : '');
								}
								break;
						}

						$caseSensitive = $filter['caseSensitive'] ?? false;
						if(!$caseSensitive && ($type == 'string' || $rawColumn) && in_array( $operator, [
								'like',
								'=',
							] )) {
							$operator = 'like';
							$searchValue = strtolower($searchValue);
							$column = DB::raw("lower($column)");
						}

						$this->populateFilterQueryWhereClause( $query, $where, $relation, function( $query ) use ( $column, $operator, $searchValue, $relation, $where ) {
							$query->{$relation ? 'where' : $where}( $column, $operator, $searchValue );
						} );
					}
				}
			}
		}
	}

	private function populateFilterQueryWhereClause( Builder $query, string $where, ?string $relation, callable $callback ) {
		if ( $relation ) {
			$query->{$where . 'Has'}( $relation, function ( $query ) use ( $callback ) {
				$callback( $query );
			} );
		} else {
			$callback( $query );
		}
	}

	public function modelHasPolicy( Model $model ): bool {
		return class_exists( 'App\\Policies\\' . class_basename( get_class( $model ) ) . 'Policy' );
	}

	public function getModelPolicyParameters( Model $model ): array {
		if( !$this->modelHasPolicy( $model ) ) {
			return [];
		}
		/** @var User $user */
		$user = Auth::user();
		$modelBaseName = class_basename( get_class( $model ) );
		$policyMethods = collect( get_class_methods( 'App\\Policies\\'.class_basename( get_class( $model ) ).'Policy' ) )
			->diff( ['before', 'allow', 'deny'] );
		return [
			Str::camel( $modelBaseName ).'Policy' => [
				'can' => $policyMethods->mapWithKeys( function( $action ) use ( $user, $model ) {
					return [$action => $user && $user->can( $action, $model )];
				} ),
			],
		];
	}

	public function checkModelPolicy( Model $model, $action ) {
		$user = Auth::user() ? Auth::user() : new User();
		if ( $this->modelHasPolicy( $model ) && $user->cannot( $action, $model ) ) {
			abort( 403 );
		}
	}

	public function getResponseTypeParameters( $type ): array {
		return $this->responseTypeParameters[ $type ] ?? [];
	}

	public function getCreateEditParameters(): array {
		return $this->createEditParameters;
	}

	public function getCreateEditParametersWithModel( $model = null ): array {
		$parameters = $this->getCreateEditParameters();
		if( !count( $parameters ) ) {
			$columns = $model
				? $model->toArray
				: $this->modelClass::getColumns()
					->except( ['id', 'slug', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by'] )
					->mapWithKeys( fn( $item, $key ) => [$key => null] )
					->toArray();
			$parameters = [Str::camel( class_basename( $this->modelClass ) ) => $columns];
		}

		return $parameters;
	}

	public function getRouteNameForAction( $modelClass, $action ): string {
		return Str::kebab( Str::plural( class_basename( $modelClass ) ) ).'.'.$action;
	}

}
