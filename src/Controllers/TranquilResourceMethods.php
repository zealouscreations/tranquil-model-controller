<?php

namespace Tranquil\Controllers;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

trait TranquilResourceMethods {

	/**
	 * Display a listing of the resource.
	 */
	public function index( Request $request ): Responsable|RedirectResponse {
		return $this->getResponse( type:'index', parameters:[
			Str::plural( Str::camel( class_basename( $this->modelClass ) ) ) => $this->getRecords( $request )['records'],
		] );
	}

	/**
	 * Show the form for creating a new resource.
	 */
	public function create(): Responsable {
		return $this->getResponse( 'create' );
	}

	/**
	 * Display the specified resource.
	 */
	public function show( mixed $model ): Responsable|RedirectResponse {
		return $this->getResponse( 'show', $this->loadModel( $model ) );
	}

	/**
	 * Show the form for editing the specified resource.
	 */
	public function edit( mixed $model ): Responsable {
		return $this->getResponse( 'edit', $this->loadModel( $model ) );
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public function update( Request $request, mixed $model ): JsonResponse|bool|\Inertia\Response|\Illuminate\Http\RedirectResponse {
		$model = $this->loadModel( $model );

		return $this->save( $request, $model->fill( $request->all() ) );
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public function store( Request $request ): bool|JsonResponse|\Inertia\Response|\Illuminate\Http\RedirectResponse {
		$model = new $this->modelClass( $request->all() );
		$this->checkModelPolicy( $model, 'create' );

		return $this->save( $request, $model );
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy( Request $request, mixed $model ): JsonResponse|\Inertia\Response|\Illuminate\Http\RedirectResponse {
		return $this->remove( $request, $this->loadModel( $model ) );
	}

	protected function loadModel( $model ): Model {
		return is_a( $model, Model::class ) ? $model : $this->modelClass::findOrFail( $model );
	}
}