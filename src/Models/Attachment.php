<?php

namespace Tranquil\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class Attachment extends TranquilModel {

	use HasFactory;

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
			if(!$attachment->wasRecentlyCreated && $attachment->isDirty('file_name')) {
				$extension = pathinfo($attachment->getOriginal('file_name'), PATHINFO_EXTENSION);
				if($extension && !str_ends_with($attachment->file_name, '.'.$extension)) {
					$attachment->file_name .= '.'.$extension;
				}
			}
		});
	}

	public function attachable(): \Illuminate\Database\Eloquent\Relations\MorphTo {
		return $this->morphTo();
	}

	public function url(): Attribute {
		return Attribute::make(
			get:fn( $value, $attributes ) => Storage::disk( 's3' )->temporaryUrl( $attributes['file_path'], now()->addMinutes( 10 ), [
				'ResponseContentDisposition' => "attachment; filename={$attributes['file_name']}",
			] )
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

	public function storeFile( UploadedFile $file ) {
		$this->file_name = $file->getClientOriginalName();
		$this->file_size = $file->getSize();
		$this->file_path = $file->store( 'attachments', 's3' );
		$this->save();
		$this->fresh();
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
