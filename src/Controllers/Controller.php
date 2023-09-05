<?php

namespace Tranquil\Controllers;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Str;

class Controller extends BaseController implements ResourceResponsesInterface {

	use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

	public static function apiResponse(bool $success, $data = [], $message = ''): JsonResponse {
		return response()->json(array_merge(compact('success', 'message'), $data));
	}

	public function redirectResponse( Request $request, Model $model = null ): bool|\Illuminate\Http\RedirectResponse {
		if( !$request->redirectRoute && !$request->redirectUrl ) {
			return false;
		}

		return $request->redirectUrl
			? redirect( $request->redirectUrl )
			: redirect()->route( $request->redirectRoute, $request->redirectParameters ?? ( $model ? [Str::camel( class_basename( $model ) ) => $model] : [] ) );
	}

	public function indexResponse( string $modelClass, $parameters = [] ): Responsable|JsonResponse {
		return self::apiResponse( true, $parameters );
	}

	public function createResponse( string $modelClass, $parameters = [] ): Responsable|JsonResponse {
		return self::apiResponse( true, $parameters );
	}

	public function showResponse( mixed $model, array $parameters = [] ): Responsable|JsonResponse {
		return self::apiResponse( true, array_merge( $parameters, [Str::camel( class_basename( $model ) ) => $model] ) );
	}

	public function editResponse( mixed $model, $parameters = [] ): Responsable|JsonResponse {
		return self::apiResponse( true, array_merge( $parameters, [Str::camel( class_basename( $model ) ) => $model] ) );
	}
}
