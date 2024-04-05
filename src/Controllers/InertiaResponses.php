<?php

namespace Tranquil\Controllers;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Trait InertiaResponses
 *
 * This is to be used with a controller that implements <strong>ResourceResponseInterface</strong>
 * This provides Inertia responses for all resource response methods
 *  <pre>indexResponse, showResponse, createResponse, editResponse</pre>
 */
trait InertiaResponses {

	/**
	 * Set this to override the path to the front-end index component
	 * @default '{plural of $modelClass}/Index'
	 * @see getComponentPath()
	 */
	public string $indexPath;

	/**
	 * Set this to override the path to the front-end create component
	 * @default '{plural of $modelClass}/CreateEdit'
	 * @see getComponentPath()
	 */
	public string $createPath;

	/**
	 * Set this to override the path to the front-end show component
	 * @default '{plural of $modelClass}/Show'
	 * @see getComponentPath()
	 */
	public string $showPath;

	/**
	 * Set this to override the path to the front-end edit component
	 * @default '{plural of $modelClass}/CreateEdit'
	 * @see getComponentPath()
	 */
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
		return Inertia::render( $this->getComponentPath( get_class( $model ), 'show' ), array_merge( [Str::camel( class_basename( $model ) ) => $model], $parameters ) );
	}

	public function editResponse( mixed $model, $parameters = [] ): Responsable {
		return Inertia::render( $this->getComponentPath( get_class( $model ), 'edit' ), array_merge( [Str::camel( class_basename( $model ) ) => $model], $parameters ) );
	}

	public function getComponentPath( string $modelClass, string $responseType ): string {
		return $this->{$responseType.'Path'} ?? ucfirst( Str::plural( Str::camel( class_basename( $modelClass ) ) ) ).'/'.ucfirst( $responseType );
	}
}