<?php

namespace Tranquil;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider {

	public function boot(): void {
		$this->loadMigrationsFrom(__DIR__.'/../database/migrations');

		$this->publishes([
			__DIR__.'/../database/migrations/' => database_path('migrations')
		], 'tranquil-model-migrations');
	}
}