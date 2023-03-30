<?php

namespace Tranquil\Controllers;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Str;
use Inertia\Inertia;

trait InertiaResponses {

	public string $indexPath;
	public string $createPath;
	public string $showPath;
	public string $editPath;

	public function __construct() {
		parent::__construct();
		if( !isset( $this->createPath ) ) {
			$this->createPath = isset( $this->modelClass ) ? $this->getComponentPath( $this->modelClass, 'createEdit' ) : null;
		}
		$this->editPath = $this->editPath ?? $this->createPath;
	}

	public function indexResponse( string $modelClass, $parameters = [] ): Responsable {
		return Inertia::render( $this->getComponentPath( $modelClass, 'index' ), $parameters );
	}

	public function createResponse( string $modelClass, $parameters = [] ): Responsable {
		return Inertia::render( $this->getComponentPath( $modelClass, 'create' ), $parameters );
	}

	public function showResponse( mixed $model, array $parameters = [] ): Responsable {
		return Inertia::render( $this->getComponentPath( get_class( $model ), 'show' ), array_merge( $parameters, [Str::camel( class_basename( $model ) ) => $model] ) );
	}

	public function editResponse( mixed $model, $parameters = [] ): Responsable {
		return Inertia::render( $this->getComponentPath( get_class( $model ), 'edit' ), array_merge( $parameters, [Str::camel( class_basename( $model ) ) => $model] ) );
	}

	public function getComponentPath( string $modelClass, string $responseType ): string {
		return $this->{$responseType.'Path'} ?? ucfirst( Str::plural( Str::camel( class_basename( $modelClass ) ) ) ).'/'.ucfirst( $responseType );
	}
}