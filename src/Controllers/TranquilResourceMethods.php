<?php

namespace Tranquil\Controllers;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Trait TranquilResourceMethods
 *
 * This provides all the methods for a Model controller
 * <pre>index, show, create, store, edit, update, destroy</pre>
 */
trait TranquilResourceMethods {

	/**
	 * Display a listing of the resource.
	 */
	public function index( Request $request ): Responsable|RedirectResponse|JsonResponse {
		return $this->getResponse( 'index' );
	}

	/**
	 * Show the form for creating a new resource.
	 */
	public function create(): Responsable|JsonResponse {
		return $this->getResponse( 'create' );
	}

	/**
	 * Display the specified resource.
	 */
	public function show( mixed $model ): Responsable|RedirectResponse|JsonResponse {
		return $this->getResponse( 'show', $this->loadModel( $model ) );
	}

	/**
	 * Show the form for editing the specified resource.
	 */
	public function edit( mixed $model ): Responsable|JsonResponse {
		return $this->getResponse( 'edit', $this->loadModel( $model ) );
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public function update( Request $request, mixed $model ): JsonResponse|bool|\Inertia\Response|RedirectResponse {
		$model = $this->loadModel( $model );

		return $this->save( $request, $model->fill( $request->all() ) );
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public function store( Request $request ): bool|JsonResponse|\Inertia\Response|RedirectResponse {
		$model = new $this->modelClass( $request->all() );
		$this->checkModelPolicy( $model, 'create' );

		return $this->save( $request, $model );
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy( Request $request, mixed $model ): JsonResponse|\Inertia\Response|RedirectResponse {
		return $this->remove( $request, $this->loadModel( $model ) );
	}

	/**
	 * Retrieve the Model if it hasn't already been retrieved from the route binding
	 */
	protected function loadModel( $model ): Model {
		return is_a( $model, Model::class ) ? $model : $this->getModelQuery( request() )->findOrFail( $model );
	}

	/**
	 * Implement this abstract method for returning a response for each response types:
	 * <pre>index, show, create, edit</pre>
	 * @see ModelController::getResponse()
	 */
	public abstract function getResponse( string $type, $model = null, $parameters = [] );

	/**
	 * Implement this abstract method for returning a list of records of the Model
	 * @see ModelController::getRecords()
	 */
	public abstract function getRecords( Request $request ): array;

	/**
	 * Implement this abstract method for persisting the Model to the database
	 * @see ModelController::save()
	 */
	public abstract function save( Request $request, mixed $model ): bool|JsonResponse|Responsable|\Illuminate\Http\RedirectResponse;

	/**
	 * Implement this abstract method for deleting the Model to the database
	 * @see ModelController::remove()
	 */
	public abstract function remove( Request $request, mixed $model ): JsonResponse|Responsable|\Illuminate\Http\RedirectResponse;

	/**
	 * Implement this abstract method for determining if the logged-in user can perform a specific action on a Model
	 * @example
	 * <pre>if( !auth()->user()->can( $action, $model ) ) { abort( 403 ); }</pre>
	 * @see ModelController::checkModelPolicy()
	 */
	public abstract function checkModelPolicy( Model $model, $action );
}