<?php


namespace Tranquil\Auth;


use Tranquil\Models\Concerns\HasColumnSchema;
use Tranquil\Models\Concerns\HasValidation;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class AuthenticatableUser extends User {

	use HasApiTokens, HasFactory, Notifiable, CanResetPassword, HasColumnSchema, HasValidation;
}