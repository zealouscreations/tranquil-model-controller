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
		Schema::create( 'attachments', function( Blueprint $table ) {
			$table->id();
			$table->timestamps();
			$table->softDeletes();
			$table->string( 'file_name', 1000 );
			$table->string( 'file_path', 2000 );
			$table->string( 'title' )->nullable();
			$table->string( 'category' )->nullable();
			$table->json( 'labels' )->nullable();
			$table->unsignedBigInteger( 'file_size' )->nullable();
			$table->nullableMorphs( 'attachable' );
		} );
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down() {
		Schema::dropIfExists( 'attachments' );
	}
};
