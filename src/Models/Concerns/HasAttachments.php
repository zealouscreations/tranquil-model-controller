<?php

namespace Tranquil\Models\Concerns;

use Tranquil\Models\Attachment;

trait HasAttachments {

	public function attachments(): \Illuminate\Database\Eloquent\Relations\MorphMany {
		return $this->morphMany(Attachment::class, 'attachable');
	}

}
