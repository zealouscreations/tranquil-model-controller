<?php

namespace Tranquil\Exceptions;

class UserRoleOptionDoesNotExistException extends \Exception {

	public function __construct($role, $options) {
		if(is_array($role)) {
			$nonExistingRoles = array_diff(
				$role,
				collect($options)->pluck('handle')->toArray()
			);
		} else {
			$nonExistingRoles = [$role];
		}
		$s = count($nonExistingRoles) > 1 ? 's' : '';
		parent::__construct("Invalid requested user role$s: '".implode("', '", $nonExistingRoles)."'");
	}
}
