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
use Tranquil\Models\Concerns\HasPolicy;
use Tranquil\Models\Concerns\HasValidation;
use Tranquil\Models\TranquilUser;
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

	/**
	 * An array of relations to eager load on the model
	 * Can optionally specify relations per response type - e.g. ['index' => ['profile'], 'show' => ['profile.address']]
	 */
	public array $loadRelations = [];

	/**
	 * An array of mutated attributes to append on the model
	 * Can optionally specify relations per response type - e.g. ['index' => ['fullName'], 'show' => ['fullName', 'fullAddress']]
	 */
	public array $loadAppends = [];

	public bool $loadPolices = true;

	/**
	 * An array of relations that should get the policy appended to
	 * This will only apply to relations that are eager loaded on the model
	 * Can optionally specify relations per response type - e.g. ['index' => ['profile'], 'show' => ['profile.address']]
	 */
	public array $loadablePolicyRelations = [];

	public array $defaultResponseParameters = [
		'index'  => [],
		'show'   => [],
		'create' => [],
		'edit'   => [],
	];
	public array $createEditParameters = [];
	public string $createdFlashMessage = 'Created';
	public string $savedFlashMessage = 'Saved';
	public string $deletedFlashMessage = 'Deleted';

	public function __construct() {
		if( !isset( $this->modelClass ) ) {
			$this->modelClass = str_replace( ['App\Http\Controllers', 'Controller'], ['App\Models', ''], get_class( $this ) );
		}
	}

	public function getResponse( string $type, $model = null, $parameters = [] ) {
		$action = match ($type) {
			'index' => 'viewAny',
			'create' => 'create',
			'show' => 'view',
			'edit' => 'update',
			default => null,
		};
		$this->checkModelPolicy( $model ?? new $this->modelClass(), $action );

		return $this->{$type.'Response'}(
			$this->getResponseModel( $type, $model ) ?? $this->modelClass,
			$this->getResponseParameters( $type, $model, $parameters )
		);
	}

	public function loadModelRelations( Model $model, ?string $responseType = null ): void {
		if( count( $this->loadRelations ) ) {
			$model->load( $this->loadRelations[ $responseType ] ?? $this->loadRelations );
		}
	}

	public function loadModelAppends( Model $model, ?string $responseType = null ): void {
		if( count( $this->loadAppends ) ) {
			$model->append( $this->loadAppends[ $responseType ] ?? $this->loadAppends );
		}
	}

	public function getResponseModel( string $type, ?Model $model ): ?Model {
		if( isset( $model ) ) {
			$this->loadModelRelations( $model, $type );
			if( $this->canLoadPolices( $model ) ) {
				$model->appendPolicies( $this->getLoadablePolicyRelations( responseType: $type ) );
			}
			$this->loadModelAppends( $model, $type );
		}

		return $model;
	}

	public function getResponseParameters( $type, $model, $parameters ): array {
		$parameters = array_merge(
			$parameters,
			$this->getResponseParametersForType( $type ),
		);
		if( in_array( $type, ['create', 'edit'] ) ) {
			$parameters = array_merge( $parameters, $this->getCreateEditParametersWithModel( $model ) );
		}
		if( $type == 'index' && $this->canLoadPolices( $model ?? new $this->modelClass() ) ) {
			$parameters['canCreate'] = auth()->user()->can( 'create', $model ?? new $this->modelClass() );
		}

		return $parameters;
	}

	public function getLoadablePolicyRelations( ?Request $request = null, ?string $responseType = null ): array {
		return collect( $request?->relations ?? $this->loadRelations )
			->map( function( $relation, $key ) {
				$relation = is_string( $relation ) ? $relation : (is_string( $key ) ? $key : null);

				return array_merge( [$relation], strpos( $relation, '.' ) > - 1 ? explode( '.', $relation ) : [] );
			} )
			->values()
			->flatten( 1 )
			->intersect( $this->loadablePolicyRelations[ $responseType ] ?? $this->loadablePolicyRelations )
			->toArray();
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
		DB::transaction( function() use ( $request, $model ) {
			$this->saveRelations( $request->input(), $model );
			$model->save();
			$this->saveAttachments( $request, $model );
		} );
		session()->flash('message', $model->wasRecentlyCreated ? $this->createdFlashMessage : $this->savedFlashMessage);
		return $model->wasRecentlyCreated
			? $this->stored( $model->fresh() )
			: $this->saved( $model->fresh() );
	}

	public function stored( Model $model ): bool|JsonResponse|Responsable|\Illuminate\Http\RedirectResponse {
		return $this->saved( $model );
	}

	public function saved( Model $model ): bool|JsonResponse|Responsable|\Illuminate\Http\RedirectResponse {
		$request = request();
		$responseModel = $this->getResponseModel( 'show', $model );
		if( !$request->header( 'X-Inertia' ) && ($request->expectsJson() || !method_exists( $this->modelClass, 'show' )) ) {
			return $this->apiResponse( true, [Str::camel( class_basename( $model ) ) => $responseModel] );
		}
		$routeName = $this->getRouteNameForAction( get_class( $model ), 'show' );
		$redirect = $this->redirectResponse( request(), $responseModel ) ?: (
			Route::has( $routeName )
				? redirect()->route( $routeName, [Str::camel( class_basename( $model ) ) => $responseModel] )
				: false
		);

		return $redirect ?: (
			method_exists( $this, 'show' )
				? $this->show( $model )
				: $this->showResponse( $responseModel )
			);
	}

	/**
	 * Saves related models that are included in the request
	 */
	public function saveRelations( array $inputs, Model $model ): void {
		$relationsToCreate = [];
		$relationsToUpdateOrCreate = [];
		$relationsToSync = [];
		$createdAssociatedRelation = false;
		foreach( $inputs as $relatedColumn => $input ) {
			if( is_array( $input ) && method_exists( $model, $relatedColumn ) && is_a( $model->$relatedColumn(), Relation::class ) ) {
				$hasOne = is_a( $model->$relatedColumn(), HasOne::class ) || is_a( $model->$relatedColumn(), MorphTo::class ) || is_a( $model->$relatedColumn(), MorphOne::class );
				$belongsTo = is_a( $model->$relatedColumn(), BelongsTo::class );
				$belongsToMany = is_a( $model->$relatedColumn(), BelongsToMany::class ) || is_a( $model->$relatedColumn(), MorphToMany::class );
				$hasMany = is_a( $model->$relatedColumn(), HasMany::class ) || is_a( $model->$relatedColumn(), MorphMany::class );
				if( $hasOne || $belongsTo ) {
					if( isset( $model->$relatedColumn ) ) {
						$model->$relatedColumn->fill( $input );
						$model->$relatedColumn->save();
						$this->saveRelations( $input, $model->$relatedColumn );
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
								$this->saveRelations( $input, $relatedModel );
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
			$model->wasRecentlyCreated = false;
		}
		foreach($relationsToCreate as $relation) {
			$relatedColumn = $relation['relatedColumn'];
			$foreignKey = $model->$relatedColumn()->getForeignKeyName();
			$relation['input'][ $foreignKey ] = $model->getKey();
			$relatedModel = $model->$relatedColumn()->create( $relation['input'] );
			$this->saveRelations( $relation['input'], $relatedModel );
		}
		foreach($relationsToUpdateOrCreate as $relation) {
			$relatedColumn = $relation['relatedColumn'];
			$input = (array) $relation['relationInput'];
			if( isset( $relation['foreignKey'] ) ) {
				$input[ $relation['foreignKey'] ] = $model->getKey();
			}
			$relatedModel = $model->$relatedColumn()->updateOrCreate( ['id' => $input['id'] ?? null], $input );
			$this->saveRelations( $input, $relatedModel );
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
		session()->flash('message', $this->deletedFlashMessage);
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

	/**
	 * @deprecated Use getModelRecordsQuery
	 */
	public function getModelQuery( Request $request ): Relation|Builder {
		return $this->getModelRecordsQuery( $request );
	}

	/**
	 * Retrieve the Model query Builder for fetching the model records
	 *
	 * Optional request parameters:
	 * $request->select -- string|array -- for selecting specific columns
	 * $request->search -- array -- see notes on the addQueryFilters method
	 * $request->orderBy -- string -- for ordering records by column(s)
	 * $request->where -- array -- a single where clause to add to the query Builder
	 * $request->relations -- array -- for eager loading related models
	 */
	public function getModelRecordsQuery( Request $request ): Relation|Builder {
		if( isset( $this->modelQuery ) ) {
			return $this->modelQuery;
		}

		$query = $this->modelClass::query();

		if( $request->select ) {
			$query->select( $request->select );
		}
		if( $request->search ) {
			$this->addQueryFilters( $query, $request->search );
		}
		if( $request->orderBy ) {
			$query->orderByRaw( $request->orderBy );
		}
		if( $request->where ) {
			$query->where( $request->where );
		}
		$relations = $request->relations ?? $this->loadRelations;
		if( count( $relations ) ) {
			$query->with( $relations );
		}

		return $query;
	}

	/**
	 * Return and api response with a list of records for the model and the total count
	 *
	 * See notes on the getRecords method for optional request parameters
	 */
	public function list( Request $request ): JsonResponse {
		return $this->apiResponse( true, $this->getRecords( $request ) );
	}

	/**
	 * Retrieve a list of records for the model and the total count
	 *
	 * Optional request parameters for the query Builder (see getModelRecordsQuery method):
	 * $request->select -- string|array -- for selecting specific columns
	 * $request->search -- array -- see notes on the addQueryFilters method
	 * $request->orderBy -- string -- for ordering records by column(s)
	 * $request->where -- array -- a single where clause to add to the query Builder
	 * $request->relations -- array -- for eager loading related models
	 *
	 * Optional request parameters for the Collection returned from the query Builder:
	 * $request->sortBy -- string -- for ordering list by column(s) via the returned Collection from the query Builder
	 * $request->descending -- bool -- (default=false) used with $request->sortBy to set the sort direction
	 * $request->offset -- int -- the index at which to start the list from
	 * $request->limit -- int -- the maximum number of records to return
	 */
	public function getRecords( Request $request ): array {
		$query = $this->getModelRecordsQuery( $request );
		$records = $this->getRecordsFromQuery( $query );
		$total = $records->count();
		$records = $this->sortRecords( $records, $request );
		$records = $records
			->slice( $request['offset'] ?: 0 )
			->take( $request['limit'] ?: 200 )
			->values();
		if( $records->count() && $this->canLoadPolices( $records->first() ) ) {
			$records = $records->map->appendPolicies( $this->getLoadablePolicyRelations( request: $request ) );
		}
		$records = $records->all();

		return compact( 'total', 'records' );
	}

	public function getRecordsFromQuery( Builder $query ): Collection {
		return $this->modelUsesPolicy( new $this->modelClass() )
			? $query->whereCan( 'view' )
			: $query->get();
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
				$lastDotPosition = $rawColumn ? null : strrpos( $column, '.' );
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
									$searchValue =
										(in_array( $filter['operator'], ['endsWith', 'contains', 'like'] ) ? '%' : '')
										.$searchValue.
										(in_array( $filter['operator'], ['startsWith', 'contains', 'like'] ) ? '%' : '');
								}
								break;
						}

						$caseSensitive = $filter['caseSensitive'] ?? false;
						if(!$caseSensitive
						   && $type == 'string'
						   && !$rawColumn
						   && in_array( $operator, ['like', '='] )
						) {
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

	public function checkModelPolicy( Model $model, $action ) {
		$user = Auth::user() ?? new (config( 'auth.providers.users.model', TranquilUser::class ))();
		if ( $this->modelHasPolicy( $model ) && $user->cannot( $action, $model ) ) {
			abort( 403 );
		}
	}

	public function modelUsesPolicy( Model $model ): bool {
		return in_array( HasPolicy::class, class_uses_recursive( get_class( $model ) ) );
	}

	public function canLoadPolices( Model $model ): bool {
		return $this->loadPolices && $this->modelUsesPolicy( $model );
	}

	public function getDefaultResponseParameters(): array {
		return $this->defaultResponseParameters;
	}

	public function getResponseParametersForType( $type ): array {
		$defaultResponseParameters = $this->getDefaultResponseParameters();
		return $defaultResponseParameters[ $type ] ?? $defaultResponseParameters;
	}

	public function getCreateEditParameters(): array {
		return $this->createEditParameters;
	}

	public function getCreateEditParametersWithModel( $model = null ): array {
		$parameters = $this->getCreateEditParameters();
		if( !count( $parameters ) ) {
			$columns = $model
				? $model->makeHidden( ['created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by'] )
						->toArray()
				: $this->modelClass::getColumns()
					->except( ['id', 'slug', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by', 'remember_token', 'email_verified_at'] )
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
