<?php

namespace Tranquil\Models\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tranquil\Models\TranquilUser;

/**
 * Trait HasPolicy
 *
 * Models that use this will be able to:
 *  - Append a `policy` array attribute to the model, which will contain a `can` array with a list of booleans
 *    of actions that the authenticated user can take on the model:
 *
 * Example: <code>Company::find(1)->append('policy')->toArray()</code>
 *
 * Returns: <code>
 *     [
 *         'companyID' => 1,
 *         //...
 *         'policy' => [
 *             'can' => [
 *                 'viewAny' => true,
 *                 'view' => true,
 *                 'create' => true,
 *                 'update' => true,
 *                 'delete' => false,
 *                 'restore' => false,
 *                 'forceDelete' => false,
 *             ]
 *         ]
 *     ]
 * </code>
 *
 *  - Append the `policy` to the models relationships:
 *
 * Example: <code>Company::find(1)->appendPolicies(['contacts'])->toArray()</code>
 *
 * Returns: <code>
 *      [
 *          'id' => 1,
 *          //...
 *          'policy' => [
 *              'can' => [
 *                  'viewAny' => true,
 *                  'view' => true,
 *                  'create' => true,
 *                  'update' => true,
 *                  'delete' => false,
 *                  'restore' => false,
 *                  'forceDelete' => false,
 *              ]
 *          ],
 *          'contacts' => [
 *              [
 *                  'id' => 1,
 *                  //...
 *                  'policy' => [
 *                      'can' => [
 *                          'viewAny' => true,
 *                          'view' => true,
 *                          'create' => true,
 *                          'update' => true,
 *                          'delete' => false,
 *                          'restore' => false,
 *                          'forceDelete' => false,
 *                      ]
 *                  ]
 *              ],
 *              [
 *                  'id' => 2,
 *                  //...
 *                  'policy' => [
 *                      'can' => [
 *                          'viewAny' => true,
 *                          'view' => true,
 *                          'create' => true,
 *                          'update' => true,
 *                          'delete' => false,
 *                          'restore' => false,
 *                          'forceDelete' => false,
 *                      ]
 *                  ]
 *              ]
 *          ]
 *      ]
 *  </code>
 *
 *  - Return a filtered model collection at the end of a model query builder that only contains records which
 *    the authenticated user can take the specified `$action` on:
 *
 * Example: <code>Company::where('created_at', '>', '2024-01-01')->getWhereCan('view')</code>
 *
 * Returns: {Illuminate\Database\Eloquent\Collection<Company>}
 *
 * Note: This could increase load time for queries that return large amounts of records
 *
 *  - Add a query filter to the models query builder for only returning records that the authenticated user
 *    can take the specified `$action` on:
 *
 *  This requires a scope query method with the action name be added to the model policy.
 *
 *  <code>
 *  class CompanyPolicy {
 *      //...
 *      public function scopeView( Builder $companyQuery, User $user ): Builder {
 *          return $companyQuery->whereIn( 'companies.id', $user->companies->pluck( 'id' ) );
 *      }
 *  }
 *  </code>
 *
 * Then you can use the scope as such:
 * <code>
 *     Company::where('created_at', '>', '2024-01-01')->whereCan('view')->get()
 * </code>
 *
 * Note: Using the `whereCan` query builder scope is more efficient for queries that return large amounts of records
 *
 *
 *
 *
 * @property-read array $policy {@see self::getPolicyAttribute()}
 * An array of what actions the authenticated user can take the Model
 *
 * @method self|Collection getWhereCan(array|string $action) - Query scope for returning a collection containing only records that the authenticated user can take the specified `$action` on
 * @see self::scopeGetWhereCan()
 * @method self|Builder whereCan(array|string $action) - Query scope for applying a filter to the query builder to only return records that the authenticated user can take the specified `$action` on
 * @see self::scopeWhereCan()
 */
trait HasPolicy {

	public function getPolicyAttribute(): array {
		$user = Auth::user() ?? new (config( 'auth.providers.users.model', TranquilUser::class ))();
		$policyMethods = collect( get_class_methods( 'App\\Policies\\'.class_basename( get_class( $this ) ).'Policy' ) )
			->diff( ['before', 'allow', 'deny'] )
			->filter( fn( $method ) => !Str::startsWith( $method, 'scope' ) && !Str::startsWith( $method, '__' ) );

		return [
			'can' => $policyMethods->mapWithKeys( function( $action ) use ( $user ) {
				return [$action => $user && $user->can( $action, $this )];
			} ),
		];
	}

	/**
	 * This is the query scoped version of {@see HasPolicy::appendPolicies()}
	 * @method appendPolicies( array $relations = [] )
	 */
	public static function scopeAppendPolicies( Builder $query, array $relations = [] ): Collection|\Illuminate\Support\Collection {
		return $query->get()->append( 'policy' )->map( function( Model $model ) use ( $relations ) {
			return $model->appendPolicies( $relations );
		} );
	}

	public function appendPolicies( array $relations = [] ): static {
		$this->append( 'policy' );
		foreach( $relations as $baseRelation ) {
			$subRelations = explode( '.', $baseRelation );
			$relationModel = $this;
			foreach( $subRelations as $relation ) {
				$relationModel = is_a( $relationModel, Collection::class )
					? $relationModel->pluck( $relation )
					: $relationModel?->$relation;
				if( $relationModel ) {
					if( is_a( $relationModel, \Illuminate\Support\Collection::class ) ) {
						$relationModel->map->append( 'policy' );
					} else {
						$relationModel->append( 'policy' );
					}
				}
			}
		}

		return $this;
	}

	public function scopeGetWhereCan( Builder $query, string|array $action ): Collection {
		$actions = is_array( $action ) ? $action : [$action];
		$user = Auth::user() ?? new (config( 'auth.providers.users.model', User::class ))();

		return $query->get()->filter( function( $model ) use ( $actions, $user ) {
			foreach( $actions as $action ) {
				if( $user->cannot( $action, $model ) ) {
					return false;
				}
			}

			return true;
		} );
	}

	public function scopeWhereCan( Builder $query, string|array $action ): Builder {
		$actions = is_array( $action ) ? $action : [$action];
		$user = Auth::user() ?? new (config( 'auth.providers.users.model', User::class ))();
		$policyClass = str_replace('App\Models', 'App\Policies', static::class.'Policy');

		if(class_exists($policyClass)) {
			$modelPolicy = new $policyClass();
			foreach ($actions as $action) {
				if(
					method_exists($modelPolicy, 'scope'.$action) && (
						!method_exists($modelPolicy, 'before') ||
						!$modelPolicy->before( $user, $action )
					)
				) {
					$query = $modelPolicy->{'scope'.$action}($query, $user);
				}
			}
		}

		return $query;
	}
}