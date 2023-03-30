<?php

namespace Tranquil\Tests\Unit;

use Illuminate\Validation\ValidationException;
use Tranquil\Models\TranquilModel;
use Tranquil\Tests\TestCase;

class ModelValidationTest extends TestCase {

	protected function defineDatabaseMigrations() {
		$this->loadMigrationsFrom(__DIR__.'/../database/migrations/2023_03_30_000000_create_test_validation_models_table.php');
	}

	public function test_will_throw_validation_error_when_supplied_parameter_is_invalid() {
		$model = new TestValidationModel(['required_column' => null]);
		$this->expectException(ValidationException::class);
		$model->validate();
	}

	public function test_will_throw_validation_error_when_required_parameters_are_missing_on_new_model() {
		$model = new TestValidationModel();
		$this->expectException(ValidationException::class);
		$model->validate();
	}

	public function test_will_throw_validation_error_when_required_parameters_are_missing_on_existing_model() {
		$model = TestValidationModel::create(['required_column' => 'test']);
		$model = $model->fresh();
		$model->required_column = null;
		$this->expectException(ValidationException::class);
		$model->validate();
	}

	public function test_will_pass_validation_when_no_parameters_have_changed_on_existing_model() {
		$model = TestValidationModel::create(['required_column' => 'test']);
		$model = $model->fresh();
		$validator = $model->getValidator();
		$this->assertTrue($validator->passes());
	}

	public function test_will_pass_validation_when_supplied_parameter_is_valid() {
		$model = new TestValidationModel(['required_column' => 'test']);
		$validator = $model->getValidator();
		$this->assertTrue($validator->passes());
	}
}

class TestValidationModel extends TranquilModel {
	public $timestamps = false;
	protected $fillable = ['required_column', 'nullable_column'];
}
