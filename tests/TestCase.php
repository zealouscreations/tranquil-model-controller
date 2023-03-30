<?php

namespace Tranquil\Tests;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Storage;
use Tranquil\Models\TranquilUser;
use Mockery;

class TestCase extends \Orchestra\Testbench\TestCase {

	use WithFaker;

	public bool|string $requiresAuthenticatedUser = false;
	public bool $fakesFileSystem = false;

	public TranquilUser $authUser;

	protected function getPackageProviders( $app ): array {
		return [
			\Tranquil\ServiceProvider::class,
		];
	}

	protected function getEnvironmentSetUp( $app ): void {
		$app['config']->set( 'database.default', 'sqlite' );
		$app['config']->set( 'database.connections.sqlite', [
			'driver'   => 'sqlite',
			'database' => ':memory:',
			'prefix'   => '',
		] );
	}

	protected function setUp(): void {
		parent::setUp();

		$this->artisan('cache:clear');

		if( $this->requiresAuthenticatedUser ) {
			$this->actingAsUserWithRole( $this->requiresAuthenticatedUser );
		}

		if( $this->fakesFileSystem ) {
			$mockedFakeFilesystem = Mockery::mock( Storage::fake( 's3' ) );
			$mockedFakeFilesystem->shouldReceive( 'temporaryUrl' )
								 ->andReturn( $this->faker->url() );
			Storage::set( 's3', $mockedFakeFilesystem );
		}
	}

	public function actingAsUserWithRole( $role = null ) {
		$roles = is_array( $role ) ? $role : (is_string( $role ) ? [$role] : ['user']);
		$this->authUser = TranquilUser::factory()->create( ['roles' => $roles] );
		$this->actingAs( $this->authUser );
	}
}