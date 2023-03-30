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
		Schema::create('test_controller_models', function(Blueprint $table) {
			$table->id();
			$table->string('title');
			$table->string('description')->nullable();
			$table->boolean('is_active')->default(true);
			$table->date('active_on')->nullable();
			$table->foreignId('test_controller_belongs_to_model_id')->nullable();
			$table->softDeletes();
		});

		Schema::create('test_controller_belongs_to_models', function(Blueprint $table) {
			$table->id();
			$table->string('title');
			$table->softDeletes();
		});

		Schema::create('test_controller_belongs_to_model_test_controller_model', function(Blueprint $table) {
			$table->foreignId('test_controller_belongs_to_model_id')->nullable();
			$table->foreignId('test_controller_model_id')->nullable();
			$table->string('test_pivot_column')->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down() {
		Schema::dropIfExists( 'test_controller_models' );
		Schema::dropIfExists( 'test_controller_belongs_to_models' );
		Schema::dropIfExists( 'test_controller_belongs_to_model_test_controller_model' );
	}
};
