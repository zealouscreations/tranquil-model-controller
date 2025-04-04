<?php

namespace Tranquil\Models;

use Illuminate\Support\Collection;
use Tranquil\Auth\AuthenticatableUser;
use Tranquil\Exceptions\UserRoleOptionDoesNotExistException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|self whereHasAllRoles($roles)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|self whereHasRole($role)
 */
class TranquilUser extends AuthenticatableUser {

	public const roleOptions = [
		[
			'handle'      => 'super',
			'name'        => 'Super User',
			'description' => 'Has full access',
		],
		[
			'handle'      => 'basic',
			'name'        => 'Basic User',
			'description' => 'Has limited access',
		],
	];
	public const roleOptionHandleKey = 'handle';

	protected $guarded = ['id', 'created_at', 'updated_at', 'email_verified_at', 'remember_token'];

	/**
	 * The attributes that should be hidden for serialization.
	 *
	 * @var array<int, string>
	 */
	protected $hidden = [
		'password',
		'remember_token',
	];

	/**
	 * The attributes that should be cast.
	 *
	 * @var array<string, string>
	 */
	protected $casts = [
		'email_verified_at' => 'datetime',
		'roles'             => 'json',
	];

	public array $validationMessages = [
		'email.unique' => 'There is already a user with this email',
	];

	protected static function boot() {
		parent::boot();
		static::creating( function( TranquilUser $user ) {
			if( empty( $user->roles ) ) {
				$user->roles = ['basic'];
			}
		} );
		static::saving( function( TranquilUser $user ) {
			if( $user->isDirty( 'password' ) ) {
				$user->password = Hash::make( $user->password );
			}
			if( $user->isDirty( 'password_confirmation' ) ) {
				unset($user->attributes['password_confirmation']);
			}
		} );
	}

	public function getValidationRules(): array {
		return [
			'password' => 'sometimes|required|min:8|confirmed',
			'email'    => [
				'required',
				Rule::unique( 'users' )->ignore( $this->id ),
			],
		];
	}

	public function getDefaultValidationAttributes(): array {
		$attributes = parent::getDefaultValidationAttributes();
		if(request()->has('password_confirmation')) {
			$attributes['password_confirmation'] = request()->password_confirmation;
		}
		return $attributes;
	}

	public function name(): Attribute {
		return Attribute::make(
			get: fn($value, $attributes) => $attributes['first_name'].' '.$attributes['last_name']
		);
	}

	/**
	 * @param  mixed  $role
	 *
	 * @return bool
	 */
	public function hasRole( $role ): bool {
		return is_array( $role )
			? collect( $role )->some( fn( $r ) => collect( $this->roles )->contains( $r ) )
			: collect( $this->roles )->contains( $role );
	}

	public function hasAllRoles( array $roles ): bool {
		return collect( $roles )->every( fn( $role ) => collect( $this->roles )->contains( $role ) );
	}

	/**
	 * @param  string|array  $role
	 *
	 * @return void
	 * @throws UserRoleOptionDoesNotExistException
	 */
	public function addRole( $role ) {
		if ( ! self::hasRoleOption( $role ) ) {
			throw new UserRoleOptionDoesNotExistException( $role, self::getRoleOptions() );
		}
		$this->roles = collect( $this->roles )
			->merge( is_array( $role ) ? $role : [ $role ] )
			->unique()
			->values()
			->toArray();
	}

	/**
	 * @return void
	 * @throws UserRoleOptionDoesNotExistException
	 */
	public function addRoles( array $roles ) {
		$this->addRole( $roles );
	}

	/**
	 * @param  string|array  $role
	 *
	 * @return void
	 */
	public function removeRole( $role ) {
		$this->roles = collect( $this->roles )
			->diff( is_array( $role ) ? $role : [ $role ] )
			->values()
			->toArray();
	}

	public function removeRoles( array $roles ) {
		$this->removeRole( $roles );
	}

	public static function getRoleOptions(): Collection {
		return collect( static::roleOptions );
	}

	/**
	 * @param  string|array  $roleHandle
	 *
	 * @return bool
	 */
	public static function hasRoleOption( $roleHandle ): bool {
		if ( is_array( $roleHandle ) ) {
			return static::hasAllRoleOptions( $roleHandle );
		}

		return static::getRoleOptions()->pluck( static::roleOptionHandleKey )->contains( $roleHandle );
	}

	public static function hasAllRoleOptions( array $roleHandles ): bool {
		return ! array_diff( $roleHandles, static::getRoleOptions()->pluck( static::roleOptionHandleKey )->toArray() );
	}

	/**
	 * whereHasRole($role)
	 *
	 * @param Builder $query
	 * @param string|array $role
	 * @return void
	 */
	public function scopeWhereHasRole( $query, $role ) {
		$roles = is_array($role) ? $role : [$role];
		$query->whereJsonContains('roles', array_shift($roles));
		foreach($roles as $role) {
			$query->orWhereJsonContains('roles', $role);
		}
	}

	/**
	 * whereHasAllRoles($roles)
	 *
	 * @param Builder $query
	 * @param array $roles
	 * @return void
	 */
	public function scopeWhereHasAllRoles( $query, $roles ) {
		foreach($roles as $role) {
			$query->whereJsonContains('roles', $role);
		}
	}
}
