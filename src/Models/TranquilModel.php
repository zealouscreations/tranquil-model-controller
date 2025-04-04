<?php


namespace Tranquil\Models;


use Tranquil\Models\Concerns\HasValidation;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|self overrideAppends($appends)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|self withoutAppends()
 */
class TranquilModel extends Model {

	use HasValidation;

	protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

	public $timestamps = true;

	public static $snakeAttributes = false;

	public static bool $deletableAsHasMany = false;

	public static $appendsScopedToModel = null;
	public static $scopedAppends = null;
	public static $addedAppends = null;
	public static $withoutAppends = false;

	public function scopeOverrideAppends($query, $appends) {
		self::$appendsScopedToModel = get_class($this);
		self::$scopedAppends = $appends;
		return $query;
	}

	public function scopeAddAppends($query, $appends) {
		self::$appendsScopedToModel = get_class($this);
		self::$addedAppends = $appends;
		return $query;
	}

	public function scopeWithoutAppends($query) {
		self::$appendsScopedToModel = get_class($this);
		self::$withoutAppends = true;
		return $query;
	}

	protected function getArrayableAppends() {
		if(self::$appendsScopedToModel == get_class($this)) {
			if( self::$withoutAppends ) {
				return [];
			}
			if( isset( self::$scopedAppends ) ) {
				return self::$scopedAppends;
			}
			if( isset( self::$addedAppends ) ) {
				$this->addAppends( self::$addedAppends );
			}
		}
		return parent::getArrayableAppends();
	}

	/**
	 * @param array|string $appends
	 * @return mixed
	 */
	public function addAppends( $appends ) {
		$this->appends = array_merge( is_array( $appends ) ? $appends : [$appends], $this->appends );
		return $this;
	}
}