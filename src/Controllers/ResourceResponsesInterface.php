<?php

namespace Tranquil\Controllers;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

interface ResourceResponsesInterface {

	public function indexResponse( string $modelClass, $parameters = [] ): Responsable|JsonResponse;

	public function createResponse( string $modelClass, $parameters = [] ): Responsable|JsonResponse;

	public function showResponse( mixed $model, array $parameters = [] ): Responsable|JsonResponse;

	public function editResponse( mixed $model, $parameters = [] ): Responsable|JsonResponse;
}