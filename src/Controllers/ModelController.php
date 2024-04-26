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

/**
 * Class ModelController
 *
 * A base controller for handling all Model resource operations
 */
class ModelController extends Controller implements ResourceResponsesInterface {

	/**
	 * The fully qualified class name of the Model this controller references
	 * @default The beginning of the controller name
	 * @example \App\Http\Controllers\UserController => \App\Models\User
	 * @see __construct()
	 */
	public string $modelClass;

	/**
	 * The query builder that will be used for fetching the list of model records
	 * @see ModelController::getModelQuery()
	 * @see ModelController::getModelRecordsQuery()
	 */
	public Builder|Relation $modelQuery;

	/**
	 * An array of relations to eager load on the model
	 * Can optionally specify relations per response type
	 * @example <code>['index' => ['profile'], 'show' => ['profile.address']]</code>
	 * @see ModelController::getLoadRelations()
	 */
	public array $loadRelations = [];

	/**
	 * An array of mutated attributes to append on the model
	 * Can optionally specify relations per response type
	 * @example <code>['index' => ['fullName'], 'show' => ['fullName', 'fullAddress']]</code>
	 * @see ModelController::getLoadAppends()
	 */
	public array $loadAppends = [];

	/**
	 * A flag to use to bypass the loading of policies
	 * This can also be overridden by including <code>excludePolicies</code> in the http request
	 * @see ModelController::canLoadPolices()
	 */
	public bool $loadPolices = true;

	/**
	 * An array of relations that should get the policy appended to.
	 * This will only apply to relations that are eager loaded on the model.
	 * Can optionally specify relations per response type.
	 * @example <code>['index' => ['profile'], 'show' => ['profile.address']]</code>
	 * @see ModelController::getLoadablePolicyRelations()
	 */
	public array $loadablePolicyRelations = [];

	/**
	 * An array of the default parameters to be passed to the response for each response type.
	 * @see ModelController::getDefaultResponseParameters()
	 * @see ModelController::getResponseParameters()
	 * @see ModelController::getResponseParametersForType()
	 */
	public array $defaultResponseParameters = [
		'index'  => [],
		'show'   => [],
		'create' => [],
		'edit'   => [],
	];

	/**
	 * An array of the default parameters to be passed to the <pre>create</pre> and <pre>edit</pre> responses.
	 * This would typically be the Model with all the model parameters you want to be operated on,
	 * plus any other parameters you want to be available.
	 * @see ModelController::getCreateEditParameters()
	 * @see ModelController::getCreateEditParametersWithModel()
	 */
	public array $createEditParameters = [];

	/**
	 * The message to be flashed after a Model is created
	 */
	public string $createdFlashMessage = 'Created';

	/**
	 * The message to be flashed after a Model is saved
	 */
	public string $savedFlashMessage = 'Saved';

	/**
	 * The message to be flashed after a Model is deleted
	 */
	public string $deletedFlashMessage = 'Deleted';

	public string $controllerNamespace = 'App\Http\Controllers';

	public string $modelNamespace = 'App\Models';

	public function __construct() {
		if( !isset( $this->modelClass ) ) {
			$this->modelClass = str_replace( [$this->controllerNamespace, 'Controller'], [$this->modelNamespace, ''], get_class( $this ) );
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

	public function getLoadRelations( ?string $responseType = null ): array {
		return collect( $this->loadRelations )->hasAny( ['index', 'show', 'create', 'edit', 'list'] )
			? $this->loadRelations[ $responseType ] ?? []
			: $this->loadRelations;
	}

	public function loadModelRelations( Model $model, ?string $responseType = null ): void {
		$relations = $this->getLoadRelations( $responseType );
		if( count( $relations ) ) {
			$model->load( $relations );
		}
	}

	public function getLoadAppends( ?string $responseType = null ): array {
		return collect( $this->loadAppends )->hasAny( ['index', 'show', 'create', 'edit', 'list'] )
			? $this->loadAppends[ $responseType ] ?? []
			: $this->loadAppends;
	}

	public function loadModelAppends( Model $model, ?string $responseType = null ): void {
		$appends = $this->getLoadAppends( $responseType );
		if( count( $appends ) ) {
			$model->append( $appends );
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
		$loadablePolicyRelations = collect( $this->loadablePolicyRelations )->hasAny( ['index', 'show', 'create', 'edit', 'list'] )
			? $this->loadablePolicyRelations[ $responseType ] ?? []
			: $this->loadablePolicyRelations;

		return collect( $request?->relations ?? $this->getLoadRelations( $responseType ) )
			->map( function( $relation, $key ) {
				$relation = is_string( $relation ) ? $relation : (is_string( $key ) ? $key : null);

				return array_merge( [$relation], strpos( $relation, '.' ) > - 1 ? explode( '.', $relation ) : [] );
			} )
			->values()
			->flatten( 1 )
			->intersect( $loadablePolicyRelations )
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

	/**
	 * Method for hooking into after a model has been stored and before it returns a response from the saved method
	 */
	public function stored( Model $model ): bool|JsonResponse|Responsable|\Illuminate\Http\RedirectResponse {
		return $this->saved( $model );
	}

	public function saved( Model $model ): bool|JsonResponse|Responsable|\Illuminate\Http\RedirectResponse {
		$request = request();
		$responseModel = $this->getResponseModel( 'show', $model );
		if( !$request->header( 'X-Inertia' ) && ($request->expectsJson() || !method_exists( $this, 'show' )) ) {
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
				$relatedPrimaryKey = $model->$relatedColumn()->getModel()->getKeyName();
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
							if( isset( $input[$relatedPrimaryKey] ) ) {
								$relatedModel = $model->$relatedColumn()->getModel()->where( $relatedPrimaryKey, $input[$relatedPrimaryKey] )->first();
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
							if( $inputCollection->pluck( $relatedPrimaryKey )->filter()->count() ) {
								$pivotField = $model->$relatedColumn()->getPivotAccessor();
								$pivotColumns = $model->$relatedColumn()->getPivotColumns();
								$input = $inputCollection->mapWithKeys( function( $item, $key ) use ( $pivotField, $pivotColumns, $relatedPrimaryKey ) {
									$itemArray = (array) $item;
									$id = $itemArray[$relatedPrimaryKey] ?? (is_numeric( $item ) ? $item : $key);
									$pivotAttributes = isset($itemArray[ $pivotField ])
										? Arr::only( $itemArray[ $pivotField ], [$relatedPrimaryKey, ...$pivotColumns] )
										: null;
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
			$relatedPrimaryKey = $model->$relatedColumn()->getModel()->getKeyName();
			$relatedModel = $model->$relatedColumn()->updateOrCreate( [$relatedPrimaryKey => $input[$relatedPrimaryKey] ?? null], $input );
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
	 * Use <code>$request->permanent = true</code> to permanently remove from the database<br>
	 * Use <code>$request->redirectRoute</code> to redirect to a different route - defaults to model's index<br>
	 * Use <code>$request->redirectParameters</code> to add parameters to the redirect route - e.g. <code>['model' => 1]</code>
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
	 * <code>$request->relations</code> -- array -- for eager loading related models
	 */
	public static function getModel( Request $request, Model $model ): JsonResponse {
		return static::apiResponse( true, [ Str::camel( Str::singular( $model->getTable() ) ) => $model->load( $request->relations ?? [] ) ] );
	}

	/**
	 * Get the query builder that will be used for fetching the list of model records
	 * @see ModelController::getModelRecordsQuery()
	 */
	public function getModelQuery( Request $request ): Relation|Builder {
		if( isset( $this->modelQuery ) ) {
			return $this->modelQuery;
		}

		return $this->modelClass::query();
	}

	/**
	 * Retrieve the Model query Builder for fetching the model records
	 *
	 * Optional request parameters:
	 * <code>$request->select</code> -- string|array -- for selecting specific columns<br>
	 * <code>$request->orderBy</code> -- string -- for ordering records by column(s)<br>
	 * <code>$request->where</code> -- array -- a single where clause to add to the query Builder<br>
	 * <code>$request->relations</code> -- array -- for eager loading related models<br>
	 * <code>$request->scopes</code> -- array -- for using model query scopes
	 * <code>$request->search</code> -- array -- see notes on the addQueryFilters method<br>
	 * @see ModelController::addQueryFilters()
	 */
	public function getModelRecordsQuery( Request $request ): Relation|Builder {

		$query = $this->getModelQuery( $request );

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
		$relations = $request->relations ?? $this->getLoadRelations( 'list' );
		if( count( $relations ) ) {
			$query->with( $relations );
		}
		foreach( $request->scopes ?? [] as $index => $value ) {
			if( !is_string( $index ) && is_array( $value ) ) {
				$scope = array_key_first( $value );
				$parameters = $value[ $scope ];
			} else {
				$scope = is_string( $index ) ? $index : $value;
				$parameters = is_string( $index ) ? $value : [];
			}
			$query->{$scope}( ...(is_array( $parameters ) ? $parameters : [$parameters]) );
		}

		if( $this->modelUsesPolicy( new $this->modelClass() ) ) {
			$query->whereCan( 'view' );
		}

		return $query;
	}

	public function getQueryLimitOffset( Request $request, Builder $query ): Builder {
		if( $request->has('limit') ) {
			$query->limit( $request->limit );
		}
		if( $request->has('offset') ) {
			$query->offset( $request->offset );
		}

		return $query;
	}


	/**
	 * Return and api response with a list of records for the model and the total count
	 *
	 * See notes on the getRecords method for optional request parameters
	 * @see ModelController::getRecords()
	 */
	public function list( Request $request ): JsonResponse {
		return $this->apiResponse( true, $this->getRecords( $request ) );
	}

	/**
	 * Retrieve a list of records for the model and the total count
	 *
	 * Optional request parameters for the query Builder:
	 * @see ModelController::getModelRecordsQuery() <br>
	 * <code>$request->select</code> -- string|array -- for selecting specific columns<br>
	 * <code>$request->search</code> -- array -- see notes on the addQueryFilters method
	 * @see ModelController::addQueryFilters() <br>
	 * <code>$request->offset</code> -- int -- the index at which to start the query from<br>
	 * <code>$request->limit</code> -- int -- the maximum number of records to return from the query<br>
	 * <code>$request->orderBy</code> -- string -- for ordering records by column(s)<br>
	 * <code>$request->where</code> -- array -- a single where clause to add to the query Builder<br>
	 * <code>$request->relations</code> -- array -- for eager loading related models<br>
	 * <code>$request->scopes</code> -- array -- for using model query scopes<br><br>
	 * <hr>
	 * Optional request parameters for the Collection returned from the query Builder:<br>
	 * <code>$request->sortBy</code> -- string -- for ordering list by column(s) via the returned Collection from the query Builder<br>
	 * <code>$request->descending</code> -- bool -- (default=false) used with $request->sortBy to set the sort direction<br>
	 * <code>$request->slice</code> -- int -- the index at which to start the list from the records collection<br>
	 * <code>$request->take</code> -- int -- the maximum number of records to return from the collection<br>
	 */
	public function getRecords( Request $request ): array {
		$query = $this->getModelRecordsQuery( $request );
		$total = $query->count();
		$query = $this->getQueryLimitOffset( $request, $query );
		$records = $this->getRecordsFromQuery( $query );
		$appends = $request->appends ?? $this->getLoadAppends( 'list' );
		if( count( $appends ) ) {
			$records->append( $appends );
		}
		$records = $this->sortRecords( $records, $request );
		$records = $this->sliceTakeRecords( $records, $request );
		if( $records->count() && $this->canLoadPolices( $records->first() ) ) {
			$records = $records->map->appendPolicies( $this->getLoadablePolicyRelations( $request, 'list' ) );
		}
		$nextOffset = $request->limit ? $request->offset + $records->count() : 0;
		$records = $records->all();

		return compact( 'total', 'records', 'nextOffset' );
	}

	public function getRecordsFromQuery( Builder $query ): Collection {
		return $query->get();
	}

	public function sortRecords( Collection $records, Request $request ): Collection {
		return $request->sortBy ? $records->sortBy( $request->sortBy, SORT_REGULAR, $request->descending ) : $records;
	}

	public function sliceTakeRecords( Collection $records, Request $request ): Collection {
		if( $request->has('slice') ) {
			$records->slice( $request->slice );
		}
		if( $request->has('take') ) {
			$records->take( $request->take );
		}

		return $records->values();
	}

	/**
	 * Apply filters to the query builder.
	 * <code>$filters[
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
	 * ]</code>
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
				if( !$rawColumn ) {
					$lastDotPosition = strrpos( $column, '.' );
					if( $lastDotPosition ) {
						// One level deep relation search 'relationName.column_name'
						$relation = substr( $column, 0, $lastDotPosition );
						$column = substr( $column, $lastDotPosition + 1 );
					} else if( $query->getQuery()->joins ) {
						$column = $query->getModel()->getTable().'.'.$column;
					}
				}
				$operator = strtolower( $filter['operator'] ?? '' );
				$type = $filter['type'] ?? null;
				if( in_array( $operator, ['in', 'between'] ) && !in_array( $type, ['date', 'datetime'] ) ) {
					$searchValue = $filter['value'];
					$this->populateFilterQueryWhereClause( $query, $where, $relation, function( $query ) use ( $column, $where, $operator, $searchValue ) {
						$where = $where.ucfirst( $operator );
						$query->$where( $column, $searchValue );
					} );
				} else {
					$operator = isset( $filter['operator'] ) ? str_replace( [
						'startsWith',
						'contains',
						'endsWith',
					], 'like', $filter['operator'] ) : '=';
					if( !$type && !$rawColumn ) {
						/** @var Model $model */
						$model = (new $this->modelClass);
						$model = $relation ? $model->$relation()->getModel() : $model;
						if( in_array( HasColumnSchema::class, class_uses_recursive( $model ) ) ) {
							$type = $model->getColumnType( $relation ? $column : $filter['column'] );
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
								if( $operator == 'like' && !str_contains( $searchValue, '%' ) ) {
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
		return class_exists( str_replace('Models', 'Policies', get_class( $model )) . 'Policy' );
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
		return !request()->excludePolicies && $this->loadPolices && $this->modelUsesPolicy( $model );
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
		if( in_array( HasColumnSchema::class, class_uses_recursive( $model ?? $this->modelClass ) ) ) {
			$columns = $model
				? $this->getFilledModelArray( $model )
				: $this->getEmptyModelArray( $this->modelClass );
			$parameters = array_merge($parameters, [Str::camel( class_basename( $this->modelClass ) ) => $columns]);
		}

		return $parameters;
	}

	public function getFilledModelArray( $model ): array {
		return $model->makeHidden( [
			'created_at',
			'updated_at',
			'deleted_at',
			'created_by',
			'updated_by',
			'deleted_by',
		] )->toArray();
	}

	public function getEmptyModelArray( string $modelClass, array $withRelations = [] ): array {
		$model = new $modelClass();
		$emptyModel = $model::getColumns()
							->except( [
								(new $modelClass())->getKeyName(),
								'id',
								'slug',
								'created_at',
								'updated_at',
								'deleted_at',
								'created_by',
								'updated_by',
								'deleted_by',
								'remember_token',
								'email_verified_at',
							] )
							->mapWithKeys( fn( $item, $key ) => [$key => null] )
							->toArray();
		foreach( $withRelations as $relation ) {
			$subRelations = explode( '.', $relation );
			$relation = array_shift( $subRelations );
			$returnEmptyRelation = Str::contains( $relation, ':empty' );
			$relation = str_replace( ':empty', '', $relation );
			if( method_exists( $model, $relation ) && is_a( $model->$relation(), Relation::class ) ) {
				$belongsToMany = is_a( $model->$relation(), BelongsToMany::class ) || is_a( $model->$relation(), MorphToMany::class );
				$hasMany = is_a( $model->$relation(), HasMany::class ) || is_a( $model->$relation(), MorphMany::class );
				$subRelations = implode( '.', $subRelations );
				$emptyModelArray = $belongsToMany || ($hasMany && $returnEmptyRelation)
					? []
					: (
					$returnEmptyRelation ? null :
						$this->getEmptyModelArray( get_class( $model->$relation()->getModel() ), !empty( $subRelations ) ? [$subRelations] : [] )
					);
				$emptyModel[ $relation ] = $hasMany && !$returnEmptyRelation ? [$emptyModelArray] : $emptyModelArray;
			}
		}
		return $emptyModel;
	}

	public function getRouteNameForAction( $modelClass, $action ): string {
		return Str::kebab( Str::plural( class_basename( $modelClass ) ) ).'.'.$action;
	}

	/**
	 * Retrieve the Model if it hasn't already been retrieved from the route binding
	 */
	protected function retrieveModel( $model ): Model {
		return is_a( $model, Model::class ) ? $model : $this->modelClass::findOrFail( $model );
	}

	public function redirectResponse( Request $request, Model $model = null ): bool|\Illuminate\Http\RedirectResponse {
		if( !$request->redirectRoute && !$request->redirectUrl ) {
			return false;
		}

		return $request->redirectUrl
			? redirect( $request->redirectUrl )
			: redirect()->route( $request->redirectRoute, $request->redirectParameters ?? ( $model ? [Str::camel( class_basename( $model ) ) => $model] : [] ) );
	}

	public static function apiResponse(bool $success, $data = [], $message = ''): JsonResponse {
		return response()->json(array_merge(compact('success', 'message'), $data));
	}

	public function indexResponse( string $modelClass, $parameters = [] ): Responsable|JsonResponse {
		return self::apiResponse( true, $parameters );
	}

	public function createResponse( string $modelClass, $parameters = [] ): Responsable|JsonResponse {
		return self::apiResponse( true, $parameters );
	}

	public function showResponse( mixed $model, array $parameters = [] ): Responsable|JsonResponse {
		return self::apiResponse( true, array_merge( [Str::camel( class_basename( $model ) ) => $model], $parameters ) );
	}

	public function editResponse( mixed $model, $parameters = [] ): Responsable|JsonResponse {
		return self::apiResponse( true, array_merge( [Str::camel( class_basename( $model ) ) => $model], $parameters ) );
	}
}
