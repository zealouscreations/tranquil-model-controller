<?php

namespace Tranquil\Models\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Tranquil\Models\TranquilUser;

/**
 * Trait HasPolicy
 *
 * @property-read array $policy {@see self::getPolicyAttribute()}
 * An array of what actions the authenticated user can take the Model
 *
 * @method self|Builder whereCan(array|string $action) - Query scope for filtering out records that the authenticated user cannot take action on
 * @see self::scopeWhereCan()
 */
trait HasPolicy {

	public function getPolicyAttribute(): array {
		$user = Auth::user() ?? new (config( 'auth.providers.users.model', TranquilUser::class ))();
		$policyMethods = collect( get_class_methods( 'App\\Policies\\'.class_basename( get_class( $this ) ).'Policy' ) )
			->diff( ['before', 'allow', 'deny'] );

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
					: $relationModel->$relation;
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

	public function scopeWhereCan( Builder $query, string|array $action ): Collection {
		$actions = is_array( $action ) ? $action : [$action];
		$user = Auth::user() ?? new (config( 'auth.providers.users.model', TranquilUser::class ))();

		return $query->get()->filter( function( $model ) use ( $actions, $user ) {
			foreach( $actions as $action ) {
				if( $user->cannot( $action, $model ) ) {
					return false;
				}
			}

			return true;
		} );
	}
}