<?php

namespace Tranquil\Controllers;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Trait TranquilResourceViewMethods
 *
 * This provides the view methods for a Model controller
 * <pre>index, show, create, edit</pre>
 */
trait TranquilResourceViewMethods {

	/**
	 * Display a listing of the resource.
	 */
	public function index( Request $request ): Responsable|RedirectResponse|JsonResponse {
		$this->checkModelPolicy( new $this->modelClass(), 'update' );

		return $this->getResponse( 'index' );
	}

	/**
	 * Show the form for creating a new resource.
	 */
	public function create(): Responsable|JsonResponse {
		$this->checkModelPolicy( new $this->modelClass(), 'create' );

		return $this->getResponse( 'create' );
	}

	/**
	 * Display the specified resource.
	 */
	public function show( mixed $model ): Responsable|RedirectResponse|JsonResponse {
		$this->checkModelPolicy( new $this->modelClass(), 'view' );

		return $this->getResponse( 'show', $this->retrieveModel( $model ) );
	}

	/**
	 * Show the form for editing the specified resource.
	 */
	public function edit( mixed $model ): Responsable|JsonResponse {
		$this->checkModelPolicy( new $this->modelClass(), 'update' );

		return $this->getResponse( 'edit', $this->retrieveModel( $model ) );
	}

	public function authorizeResourceViewMethods(): void {
		if( $this->modelHasPolicy( new $this->modelClass() ) ) {
			$methodAbilities = [
				'index'  => 'viewAny',
				'show'   => 'view',
				'create' => 'create',
				'edit'   => 'update',
			];
			foreach( $methodAbilities as $method => $ability ) {
				$modelName = in_array( $method, ['index', 'create'] )
					? $this->modelClass
					: Str::camel( class_basename($this->modelClass) );
				$this->middleware( "can:{$ability},{$modelName}" )->only( $method );
			}
		}
	}

	/**
	 * Implement this abstract method for retrieving the Model if it hasn't already been retrieved from the route binding
	 * @see ModelController::retrieveModel()
	 */
	public abstract function retrieveModel( $model );

	/**
	 * Implement this abstract method for returning a response for each response types:
	 * <pre>index, show, create, edit</pre>
	 * @see ModelController::getResponse()
	 */
	public abstract function getResponse( string $type, $model = null, $parameters = [] );

	/**
	 * Implement this abstract method for determining if the logged-in user can perform a specific action on a Model
	 * @example
	 * <pre>if( !auth()->user()->can( $action, $model ) ) { abort( 403 ); }</pre>
	 * @see ModelController::checkModelPolicy()
	 */
	public abstract function checkModelPolicy( Model $model, $action );
}