<?php

namespace Tranquil\Models\Concerns;

use Doctrine\DBAL\Schema\Column;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

trait HasColumnSchema {

	public static function getColumns(): Collection {
		$table = (new static())->getTable();
		return Cache::remember($table.'_columns', 180, function() use ($table) {
			return collect(Schema::getConnection()->getDoctrineSchemaManager()->listTableColumns($table));
		});
	}

	/**
	 * @param string $column
	 * @return Column|null
	 */
	public static function getColumnSchema(string $column): ?Column {
		return static::getColumns()->get($column);
	}

	/**
	 * @param string $column
	 * @return string|null
	 */
	public static function getColumnType(string $column): ?string {
		$columnSchema = static::getColumnSchema($column);
		return $columnSchema ? $columnSchema->getType()->getName() : null;
	}
}