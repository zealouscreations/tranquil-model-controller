<?php

namespace Tranquil\Models\Concerns;

use Doctrine\DBAL\Schema\Column;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Trait HasColumns
 *
 * This trait is used with the {@see HasValidation} trait.
 */
trait HasColumnSchema {

	/**
	 * Get a collection of Doctrine\DBAL\Schema\Columns with the column names as keys
	 *
	 * @return Collection<Column[]>
	 */
	public static function getColumns(): Collection {
		$table = (new static())->getTable();
		return Cache::remember($table.'_columns', 180, function() use ($table) {
			return collect(Schema::getConnection()->getDoctrineSchemaManager()->listTableColumns($table))->mapWithKeys(fn($column) => [$column->getName() => $column]);
		});
	}

	public static function getColumnSchema(string $column): ?Column {
		return static::getColumns()->get($column);
	}

	public static function getColumnType(string $column): ?string {
		return  static::getColumnSchema($column)?->getType()->getName();
	}
}