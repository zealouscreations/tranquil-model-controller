<?php

namespace Tranquil\Controllers;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Trait TranquilResourceRecordMethods
 *
 * This provides the methods for fetching and storing records for a Model controller
 * <pre>store, update, destroy</pre>
 */
trait TranquilResourceRecordMethods {

	/**
	 * Update the specified resource in storage.
	 *
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public function update( Request $request, mixed $model ): JsonResponse|bool|\Inertia\Response|RedirectResponse {
		$model = $this->retrieveModel( $model );

		return $this->save( $request, $model->fill( $request->input() ) );
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public function store( Request $request ): bool|JsonResponse|\Inertia\Response|RedirectResponse {
		return $this->save( $request, new $this->modelClass( $request->input() ) );
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy( Request $request, mixed $model ): JsonResponse|\Inertia\Response|RedirectResponse {
		return $this->remove( $request, $this->retrieveModel( $model ) );
	}

	/**
	 * Implement this abstract method for retrieving the Model if it hasn't already been retrieved from the route binding
	 * @see ModelController::retrieveModel()
	 */
	public abstract function retrieveModel( $model );

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