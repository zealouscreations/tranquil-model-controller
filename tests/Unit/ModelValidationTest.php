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

	public function test_will_pass_validation_when_a_parameter_has_not_been_supplied_on_a_required_with_rule() {
		$model = new TestRequiredWithValidationModel(['required_column' => 'test']);
		$this->assertTrue($model->getValidator()->passes());
		$model = new TestRequiredWithValidationModel(['required_column' => 'test', 'nullable_column' => null]);
		$this->assertTrue($model->getValidator()->passes());
		$model = TestRequiredWithValidationModel::create(['required_column' => 'test']);
		$model->nullable_column = null;
		$this->assertTrue($model->getValidator()->passes());
	}

	public function test_will_fail_validation_when_a_parameter_has_been_supplied_on_a_required_with_rule() {
		$model = new TestRequiredWithValidationModel(['required_column' => 'test', 'another_nullable_column' => TestEnum::A]);
		$this->assertTrue($model->getValidator()->fails());
		$model = new TestRequiredWithValidationModel(['required_column' => 'test', 'nullable_column' => null, 'another_nullable_column' => TestEnum::A]);
		$this->assertTrue($model->getValidator()->fails());
		$model = TestRequiredWithValidationModel::create(['required_column' => 'test']);
		$model->another_nullable_column = TestEnum::A;
		$this->assertTrue($model->getValidator()->fails());
	}

	public function test_will_pass_validation_when_a_parameter_has_not_been_supplied_on_a_sometimes_required_rule() {
		$model = new TestSometimesValidationModel(['required_column' => 'test']);
		$this->assertTrue($model->getValidator()->passes());
		$model = TestSometimesValidationModel::create(['required_column' => 'test']);
		$model->nullable_column = null;
		$this->assertTrue($model->getValidator()->passes());
	}

	public function test_will_pass_validation_when_a_parameter_has_not_been_supplied_on_a_sometimes_required_with_rule() {
		$model = new TestSometimesValidationModel(['required_column' => 'test', 'another_nullable_column' => 'test']);
		$this->assertTrue($model->getValidator()->passes());
		$model = TestSometimesValidationModel::create(['required_column' => 'test', 'another_nullable_column' => 'test']);
		$model->nullable_column = null;
		$this->assertTrue($model->getValidator()->passes());
	}

	public function test_will_fail_validation_when_a_null_parameter_is_supplied_to_a_sometimes_required_rule() {
		$model = new TestSometimesValidationModel(['required_column' => 'test', 'another_nullable_column' => null]);
		$this->assertTrue($model->getValidator()->fails());
		$model = TestSometimesValidationModel::create(['required_column' => 'test']);
		$model->another_nullable_column = null;
		$this->assertTrue($model->getValidator()->fails());
	}

	public function test_will_fail_validation_when_a_null_parameter_is_supplied_to_a_sometimes_required_with_rule() {
		$model = new TestSometimesValidationModel(['required_column' => 'test', 'nullable_column' => null, 'another_nullable_column' => 'test']);
		$this->assertTrue($model->getValidator()->fails());
		$model = TestSometimesValidationModel::create(['required_column' => 'test']);
		$model->nullable_column = null;
		$model->another_nullable_column = 'test';
		$this->assertTrue($model->getValidator()->fails());
	}

	public function test_will_pass_validation_with_a_column_with_a_default_value_that_is_set_to_null() {
		$model = new TestDefaultValidationModel(['required_column' => 'test', 'column_with_default'=>null]);
		$this->assertTrue($model->getValidator()->passes());
		$model->save();
		$model->fresh()->fill(['column_with_default' => null]);
		$this->assertTrue($model->getValidator()->passes());
	}
}

enum TestEnum {
	case A;
	case B;
}

class TestValidationModel extends TranquilModel {
	public $timestamps = false;
	protected $fillable = ['required_column', 'nullable_column', 'another_nullable_column', 'column_with_default'];
}

class TestSometimesValidationModel extends TestValidationModel {
	protected $table = 'test_validation_models';
	public array $validationRules = [
		'nullable_column' => ['sometimes', 'required_with:another_nullable_column'],
		'another_nullable_column' => 'sometimes|required',
	];
}

class TestRequiredWithValidationModel extends TestValidationModel {
	protected $table = 'test_validation_models';
	protected $casts = ['another_nullable_column' => TestEnum::class];
	public array $validationRules = [
		'nullable_column' => 'required_with:another_nullable_column',
	];
}

class TestDefaultValidationModel extends TestValidationModel {
	protected $table = 'test_validation_models';
}
