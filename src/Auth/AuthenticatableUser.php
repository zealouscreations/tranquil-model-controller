<?php


namespace Tranquil\Auth;


use Tranquil\Models\Concerns\HasValidation;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Foundation\Auth\User;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class AuthenticatableUser extends User {

	use HasApiTokens, Notifiable, CanResetPassword, HasValidation;
}