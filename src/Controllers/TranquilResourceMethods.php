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

	use TranquilResourceViewMethods, TranquilResourceRecordMethods;

}