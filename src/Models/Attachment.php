<?php

namespace Tranquil\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon $deleted_at
 * @property string $file_name
 * @property string $file_path
 * @property int $file_size
 * @property string $category
 * @property array $labels
 * @property string $title
 * @property int $attachable_id
 * @property string $attachable_type
 * @property \Illuminate\Database\Eloquent\Model $attachable
 * @property-read  string $url {@see Attachment::url()}
 * @property-read  string $readableSize {@see Attachment::readableSize()}
 */
class Attachment extends TranquilModel {

	use SoftDeletes;

	public $timestamps = true;
	public static bool $deletableAsHasMany = true;

	protected $fillable = [
		'category',
		'labels',
		'title',
	];

	const contentCategoryOptions = [
		[
			'name' => 'Documents',
			'types' => ['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.png', '.jpg'],
			'maxSize' => '10MB',
		],
		[
			'name' => 'Videos',
			'types' => ['.mp4', '.mov', '.wmv'],
			'maxSize' => '200MB',
		],
	];

	protected $casts = [
		'labels' => 'json',
	];

	protected $appends = ['url', 'readableSize'];

	protected static function boot() {
		parent::boot();

		static::saving(function(Attachment $attachment) {
			if(!$attachment->wasRecentlyCreated && $attachment->isDirty('file_name') && $attachment->getOriginal('file_name')) {
				$extension = pathinfo($attachment->getOriginal('file_name'), PATHINFO_EXTENSION);
				if($extension && !str_ends_with($attachment->file_name, '.'.$extension)) {
					$attachment->file_name .= '.'.$extension;
				}
			}
		});

		static::forceDeleting( function( Attachment $attachment ) {
			Storage::delete( $attachment->file_path );
		} );
	}

	public function attachable(): \Illuminate\Database\Eloquent\Relations\MorphTo {
		return $this->morphTo();
	}

	public function url(): Attribute {
		return Attribute::make(
			get:fn( $value, $attributes ) => config( 'filesystems.default' ) == 's3'
				? Storage::disk( 's3' )->temporaryUrl( $attributes['file_path'], now()->addMinutes( 10 ), [
					'ResponseContentDisposition' => "attachment; filename={$attributes['file_name']}",
				] )
				: Storage::url( $attributes['file_path'] )
		);
	}

	public function readableSize(): Attribute {
		return Attribute::make(
			get:function( $value, $attributes ) {
				$fileSize = $attributes['file_size'];
				if( $fileSize > 1024 * 1024 * 1024 * 1024 ) {
					return round($fileSize / 1024 / 1024 / 1024 / 1024, 2).' TB';
				} else if( $fileSize > 1024 * 1024 * 1024 ) {
					return round($fileSize / 1024 / 1024 / 1024, 2).' GB';
				} else if( $fileSize > 1024 * 1024 ) {
					return round($fileSize / 1024 / 1024, 2).' MB';
				} else if( $fileSize > 1024 ) {
					return round($fileSize / 1024, 2).' KB';
				}
				return $fileSize ? round($fileSize, 2).' B' : null;
			}
		);
	}

	public function storeFile( UploadedFile $file ): void {
		$this->file_name = $file->getClientOriginalName();
		$this->file_size = $file->getSize();
		$this->file_path = $file->store( 'attachments' );
		$this->save();
		$this->fresh();
	}

	public function replaceFile( UploadedFile $file ): void {
		Storage::delete( $this->file_path );
		$this->storeFile( $file );
	}

	/**
	 * whereIsContent()
	 *
	 * @param $query
	 * @return mixed
	 */
	public function scopeWhereIsContent( $query ) {
		return $query->whereNull('attachable_id')
					 ->whereIn('category', collect(self::contentCategoryOptions)->pluck('name'));
	}
}
