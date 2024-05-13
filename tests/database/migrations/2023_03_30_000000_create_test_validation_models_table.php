<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up() {
		Schema::create('test_validation_models', function(Blueprint $table) {
			$table->id();
			$table->string('required_column');
			$table->string('nullable_column')->nullable();
			$table->string('another_nullable_column')->nullable();
			$table->boolean('column_with_default')->default(false);
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down() {
		Schema::dropIfExists( 'test_validation_models' );
	}
};
