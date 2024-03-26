<?php

namespace Tranquil\Tests\Unit;

use Tranquil\Controllers\ModelController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tranquil\Controllers\TranquilController;
use Tranquil\Models\TranquilModel;
use Tranquil\Tests\TestCase;

class ModelControllerTest extends TestCase {

	public TranquilController $controller;

	protected function defineDatabaseMigrations() {
		$this->loadMigrationsFrom(__DIR__.'/../database/migrations/2023_03_30_000001_create_test_controller_models_tables.php');
	}
	protected function setUp(): void {
		parent::setUp();

		$this->controller = new TranquilController();
		$this->controller->modelClass = TestControllerModel::class;

		$relatedModel = TestControllerBelongsToModel::create(['title' => 'Test Related Model']);

		TestControllerModel::create(['title' => 'Test1', 'is_active' => false, 'active_on' => '2022-01-01']);
		TestControllerModel::create(['title' => 'Test2', 'is_active' => false, 'active_on' => '2022-01-02']);
		TestControllerModel::create(['title' => 'Test3', 'description' => 'Test description 3 model', 'active_on' => '2022-03-01']);
		TestControllerModel::create(['title' => 'Test4', 'description' => 'Test description 4 model', 'active_on' => '2022-03-02']);
		TestControllerModel::create(['title' => 'Test5', 'description' => 'Test description 5 model', 'active_on' => '2022-03-03', 'test_controller_belongs_to_model_id' => $relatedModel->id]);

		Route::resource('test/controller/model', TestModelController::class);
		Route::name('test-redirect.index')->any('test/index/{model?}', function(Request $request) {
			// Laravel's auto-magic model binding routes don't work in tests, so have to manually fetch the model
			$model = TestControllerModel::find($request->model);
			return 'Test Index'.($model ? ': Test Model Title = '.$model->title : '');
		});
	}

	public function test_can_create_a_has_one_model_when_creating_a_model() {
		$input = [
			'title' => 'Test Model',
			'testControllerHasOneModel' => [
				'title'       => 'Test Has One Created Model',
				'description' => 'Test Model Description',
			],
		];
		$this->controller->modelClass = TestControllerBelongsToModel::class;
		$this->controller->store(new Request($input));
		$this->assertDatabaseHas('test_controller_models', [
			'title'       => $input['testControllerHasOneModel']['title'],
			'description' => $input['testControllerHasOneModel']['description'],
		]);
		$this->assertDatabaseHas('test_controller_belongs_to_models', [
			'title' => $input['title'],
		]);
		$testModel = TestControllerBelongsToModel::where('title',  $input['title'])->first();
		$this->assertEquals($input['testControllerHasOneModel']['title'], $testModel->testControllerHasOneModel->title);
	}
	public function test_can_create_a_morph_one_model_when_creating_a_model() {
		$input = [
			'title' => 'Test Model',
			'testControllerMorphOneModel' => [
				'title' => 'Test Has One Created Model',
			],
		];
		$this->controller->modelClass = TestControllerMorphsToModel::class;
		$this->controller->store(new Request($input));
		$this->assertDatabaseHas('test_controller_polymorphic_models', [
			'title' => $input['testControllerMorphOneModel']['title'],
		]);
		$this->assertDatabaseHas('test_controller_morphs_to_models', [
			'title' => $input['title'],
		]);
		$testModel = TestControllerMorphsToModel::where('title',  $input['title'])->first();
		$this->assertEquals($input['testControllerMorphOneModel']['title'], $testModel->testControllerMorphOneModel->title);
	}

	public function test_can_update_a_has_one_model_when_updating_a_model() {
		$testModel = TestControllerBelongsToModel::create([
			'title' => 'Test Model',
		]);
		TestControllerModel::create([
			'title'       => 'Test Has One Created Model',
			'description' => 'Test Model Description',
			'test_controller_belongs_to_model_id' => $testModel->id,
		]);

		$input = [
			'title' => 'Test Updated Model',
			'testControllerHasOneModel' => [
				'title'       => 'Test Updated Has One Model',
				'description' => 'Test Updated Model Description',			],
		];
		$this->controller->modelClass = TestControllerBelongsToModel::class;
		$this->controller->save(new Request($input), $testModel->fill($input));
		$this->assertDatabaseHas('test_controller_models', [
			'title'       => $input['testControllerHasOneModel']['title'],
			'description' => $input['testControllerHasOneModel']['description'],
		]);
		$this->assertDatabaseHas('test_controller_belongs_to_models', [
			'title' => $input['title'],
		]);
		$testModel = TestControllerBelongsToModel::where('title',  $input['title'])->first();
		$this->assertEquals($input['testControllerHasOneModel']['title'], $testModel->testControllerHasOneModel->title);
	}

	public function test_can_update_a_morph_one_model_when_updating_a_model() {
		$testModel = TestControllerMorphsToModel::create([
			'title' => 'Test Model',
		]);
		TestControllerPolymorphicModel::create([
			'title' => 'Test Has One Created Model',
			'morphable_id' => $testModel->id,
			'morphable_type' => TestControllerMorphsToModel::class,
		]);

		$input = [
			'title' => 'Test Updated Model',
			'testControllerMorphOneModel' => [
				'title' => 'Test Updated Has One Model',
			],
		];
		$this->controller->modelClass = TestControllerMorphsToModel::class;
		$this->controller->save(new Request($input), $testModel->fill($input));
		$this->assertDatabaseHas('test_controller_polymorphic_models', [
			'title'       => $input['testControllerMorphOneModel']['title'],
		]);
		$this->assertDatabaseHas('test_controller_morphs_to_models', [
			'title' => $input['title'],
		]);
		$testModel = TestControllerMorphsToModel::where('title',  $input['title'])->first();
		$this->assertEquals($input['testControllerMorphOneModel']['title'], $testModel->testControllerMorphOneModel->title);
	}

	public function test_can_create_a_belongs_to_model_when_creating_a_model() {
		$input = [
			'title'                        => 'Test Model',
			'description'                  => 'Test Model Description',
			'testControllerBelongsToModel' => [
				'title' => 'Test Belongs To Created Model',
			],
		];
		$this->controller->store(new Request($input));
		$this->assertDatabaseHas('test_controller_models', [
			'title'       => $input['title'],
			'description' => $input['description'],
		]);
		$this->assertDatabaseHas('test_controller_belongs_to_models', [
			'title' => $input['testControllerBelongsToModel']['title'],
		]);
		$testModel = TestControllerModel::where('title',  $input['title'])->first();
		$this->assertEquals($input['testControllerBelongsToModel']['title'], $testModel->testControllerBelongsToModel->title);
	}

	public function test_can_update_a_belongs_to_model_when_updating_a_model() {
		$testModel = TestControllerModel::create([
			'title'       => 'Test Model',
			'description' => 'Test Model Description',
			'testControllerBelongsToModel' => [
				'title' => 'Test Belongs To Created Model',
			],
		]);
		$testBelongsToModel = TestControllerBelongsToModel::create([
			'title' => 'Test Belongs To Created Model',
		]);
		$testModel->testControllerBelongsToModel()->associate($testBelongsToModel);
		$testModel->save();

		$input = [
			'title'       => 'Test Updated Model',
			'description' => 'Test Updated Model Description',
			'testControllerBelongsToModel' => [
				'title' => 'Test Updated Belongs To Model',
			],
		];
		$this->controller->save(new Request($input), $testModel->fill($input));
		$this->assertDatabaseHas('test_controller_models', [
			'title'       => $input['title'],
			'description' => $input['description'],
		]);
		$this->assertDatabaseHas('test_controller_belongs_to_models', [
			'title' => $input['testControllerBelongsToModel']['title'],
		]);
		$testModel = TestControllerModel::where('title',  $input['title'])->first();
		$this->assertEquals($input['testControllerBelongsToModel']['title'], $testModel->testControllerBelongsToModel->title);
	}

	public function test_can_update_belongs_to_many_pivot_data_when_updating_a_model() {
		$testModel = TestControllerModel::create([
			'title'       => 'Test Model',
			'description' => 'Test Model Description',
			'testControllerBelongsToModel' => [
				'title' => 'Test Belongs To Created Model',
			],
		]);
		$testModel->save();
		$testModel->testControllerBelongsToManyModels()->createMany([
			['title' => 'Test Belongs To Many Created Model 1'],
			['title' => 'Test Belongs To Many Created Model 2'],
		]);

		$input = [
			'title'       => 'Test Updated Model',
			'description' => 'Test Updated Model Description',
			'testControllerBelongsToManyModels' => $testModel->testControllerBelongsToManyModels->map(function($belongsToModel) {
				$belongsToModel->pivot->test_pivot_column = 'Test Updated Pivot Column '.$belongsToModel->id;
				return $belongsToModel;
			})->toArray()
		];
		$this->controller->save(new Request($input), $testModel->fill($input));
		$this->assertDatabaseHas('test_controller_models', [
			'title'       => $input['title'],
			'description' => $input['description'],
		]);
		$this->assertDatabaseHas('test_controller_belongs_to_model_test_controller_model', [
			'test_controller_model_id' => $testModel->id,
			'test_controller_belongs_to_model_id' => $input['testControllerBelongsToManyModels'][0]['id'],
			'test_pivot_column' => $input['testControllerBelongsToManyModels'][0]['pivot']['test_pivot_column'],
		]);
		$this->assertDatabaseHas('test_controller_belongs_to_model_test_controller_model', [
			'test_controller_model_id' => $testModel->id,
			'test_controller_belongs_to_model_id' => $input['testControllerBelongsToManyModels'][1]['id'],
			'test_pivot_column' => $input['testControllerBelongsToManyModels'][1]['pivot']['test_pivot_column'],
		]);
		$testModel = TestControllerModel::where('title',  $input['title'])->first();
		$this->assertEquals($input['testControllerBelongsToManyModels'][0]['title'], $testModel->testControllerBelongsToManyModels->first()->title);
	}

	public function test_can_sync_belongs_to_many_models_when_creating_a_model() {
		$testBelongsToModel1 = TestControllerBelongsToModel::create( ['title' => 'Test Belongs To Many Created Model 1']);
		$testBelongsToModel2 = TestControllerBelongsToModel::create( ['title' => 'Test Belongs To Many Created Model 2']);
		$input = [
			'title'       => 'Test Model',
			'description' => 'Test Model Description',
			'testControllerBelongsToManyModels' => [$testBelongsToModel1->toArray(), $testBelongsToModel2->toArray()]
		];

		$this->controller->store(new Request($input));

		$testModel = TestControllerModel::where('title',  $input['title'])->first();

		$this->assertContains($testBelongsToModel1->id, $testModel->testControllerBelongsToManyModels->pluck('id'));
		$this->assertContains($testBelongsToModel2->id, $testModel->testControllerBelongsToManyModels->pluck('id'));
	}

	public function test_can_sync_morph_to_many_models_when_creating_a_model() {
		$testBelongsToModel1 = TestTagModel::create( ['title' => 'Test Belongs To Many Created Model 1']);
		$testBelongsToModel2 = TestTagModel::create( ['title' => 'Test Belongs To Many Created Model 2']);
		$input = [
			'title'       => 'Test Model',
			'description' => 'Test Model Description',
			'testTagModels' => [$testBelongsToModel1->toArray(), $testBelongsToModel2->toArray()]
		];

		$this->controller->modelClass = TestPostModel::class;
		$this->controller->store(new Request($input));

		$testModel = TestPostModel::where('title',  $input['title'])->first();

		$this->assertContains($testBelongsToModel1->id, $testModel->testTagModels->pluck('id'));
		$this->assertContains($testBelongsToModel2->id, $testModel->testTagModels->pluck('id'));
	}

	public function test_can_sync_belongs_to_many_models_when_updating_a_model() {
		$testModel = TestControllerModel::create([
			'title'       => 'Test Model',
			'description' => 'Test Model Description',
		]);
		$testBelongsToModel1 = TestControllerBelongsToModel::create( ['title' => 'Test Belongs To Many Created Model 1']);
		$testBelongsToModel2 = TestControllerBelongsToModel::create( ['title' => 'Test Belongs To Many Created Model 2']);
		$this->controller->save(new Request([
			'testControllerBelongsToManyModels' => [$testBelongsToModel1->toArray(), $testBelongsToModel2->toArray()]
		]), $testModel);
		$testModel = $testModel->fresh();
		$this->assertContains($testBelongsToModel1->id, $testModel->testControllerBelongsToManyModels->pluck('id'));
		$this->assertContains($testBelongsToModel2->id, $testModel->testControllerBelongsToManyModels->pluck('id'));

		$this->controller->save(new Request([
			'testControllerBelongsToManyModels' => [$testBelongsToModel2->toArray()]
		]), $testModel);
		$testModel = $testModel->fresh();
		$this->assertNotContains($testBelongsToModel1->id, $testModel->testControllerBelongsToManyModels->pluck('id'));
		$this->assertContains($testBelongsToModel2->id, $testModel->testControllerBelongsToManyModels->pluck('id'));

		$testBelongsToModel3 = TestControllerBelongsToModel::create( ['title' => 'Test Belongs To Many Created Model 3']);
		$this->controller->save(new Request([
			'testControllerBelongsToManyModels' => [$testBelongsToModel3->toArray()]
		]), $testModel);
		$testModel = $testModel->fresh();
		$this->assertNotContains($testBelongsToModel1->id, $testModel->testControllerBelongsToManyModels->pluck('id'));
		$this->assertNotContains($testBelongsToModel2->id, $testModel->testControllerBelongsToManyModels->pluck('id'));
		$this->assertContains($testBelongsToModel3->id, $testModel->testControllerBelongsToManyModels->pluck('id'));

		$this->controller->save(new Request([
			'testControllerBelongsToManyModels' => []
		]), $testModel);
		$testModel = $testModel->fresh();
		$this->assertNotContains($testBelongsToModel1->id, $testModel->testControllerBelongsToManyModels->pluck('id'));
		$this->assertNotContains($testBelongsToModel2->id, $testModel->testControllerBelongsToManyModels->pluck('id'));
		$this->assertNotContains($testBelongsToModel3->id, $testModel->testControllerBelongsToManyModels->pluck('id'));
	}

	public function test_can_sync_morph_to_many_models_when_updating_a_model() {
		$testModel = TestPostModel::create([
			'title' => 'Test Model',
		]);
		$testBelongsToModel1 = TestTagModel::create( ['title' => 'Test Belongs To Many Created Model 1']);
		$testBelongsToModel2 = TestTagModel::create( ['title' => 'Test Belongs To Many Created Model 2']);
		$this->controller->modelClass = TestPostModel::class;
		$this->controller->save(new Request([
			'testTagModels' => [$testBelongsToModel1->toArray(), $testBelongsToModel2->toArray()]
		]), $testModel);
		$testModel = $testModel->fresh();
		$this->assertContains($testBelongsToModel1->id, $testModel->testTagModels->pluck('id'));
		$this->assertContains($testBelongsToModel2->id, $testModel->testTagModels->pluck('id'));

		$this->controller->save(new Request([
			'testTagModels' => [$testBelongsToModel2->toArray()]
		]), $testModel);
		$testModel = $testModel->fresh();
		$this->assertNotContains($testBelongsToModel1->id, $testModel->testTagModels->pluck('id'));
		$this->assertContains($testBelongsToModel2->id, $testModel->testTagModels->pluck('id'));

		$testBelongsToModel3 = TestTagModel::create( ['title' => 'Test Belongs To Many Created Model 3']);
		$this->controller->save(new Request([
			'testTagModels' => [$testBelongsToModel3->toArray()]
		]), $testModel);
		$testModel = $testModel->fresh();
		$this->assertNotContains($testBelongsToModel1->id, $testModel->testTagModels->pluck('id'));
		$this->assertNotContains($testBelongsToModel2->id, $testModel->testTagModels->pluck('id'));
		$this->assertContains($testBelongsToModel3->id, $testModel->testTagModels->pluck('id'));

		$this->controller->save(new Request([
			'testTagModels' => []
		]), $testModel);
		$testModel = $testModel->fresh();
		$this->assertNotContains($testBelongsToModel1->id, $testModel->testTagModels->pluck('id'));
		$this->assertNotContains($testBelongsToModel2->id, $testModel->testTagModels->pluck('id'));
		$this->assertNotContains($testBelongsToModel3->id, $testModel->testTagModels->pluck('id'));
	}

	public function test_can_create_has_many_models_when_creating_a_model() {
		$input = [
			'title'       => 'Test Model',
			'testControllerHasManyModels' => [
				[
					'title'       => 'Test Has Many Created Model 1',
					'description' => 'Test Model Description 1',
				],
				[
					'title'       => 'Test Has Many Created Model 2',
					'description' => 'Test Model Description 2',
				],
			],
		];
		$this->controller->modelClass = TestControllerBelongsToModel::class;
		$this->controller->store(new Request($input));
		$this->assertDatabaseHas('test_controller_models', [
			'title'       => $input['testControllerHasManyModels'][0]['title'],
			'description' => $input['testControllerHasManyModels'][0]['description'],
		]);
		$this->assertDatabaseHas('test_controller_models', [
			'title'       => $input['testControllerHasManyModels'][1]['title'],
			'description' => $input['testControllerHasManyModels'][1]['description'],
		]);
		$this->assertDatabaseHas('test_controller_belongs_to_models', [
			'title' => $input['title'],
		]);
		$testModel = TestControllerBelongsToModel::where('title',  $input['title'])->first();
		$this->assertCount(2, $testModel->testControllerHasManyModels);
		$this->assertEquals($input['testControllerHasManyModels'][0]['title'], $testModel->testControllerHasManyModels->first()->title);
	}

	public function test_can_create_morph_many_models_when_creating_a_model() {
		$input = [
			'title'       => 'Test Model',
			'testControllerMorphManyModels' => [
				[
					'title' => 'Test Has Many Created Model 1',
				],
				[
					'title' => 'Test Has Many Created Model 2',
				],
			],
		];
		$this->controller->modelClass = TestControllerMorphsToModel::class;
		$this->controller->store(new Request($input));
		$this->assertDatabaseHas( 'test_controller_polymorphic_models', [
			'title' => $input['testControllerMorphManyModels'][0]['title'],
		] );
		$this->assertDatabaseHas( 'test_controller_polymorphic_models', [
			'title' => $input['testControllerMorphManyModels'][1]['title'],
		] );
		$this->assertDatabaseHas('test_controller_morphs_to_models', [
			'title' => $input['title'],
		]);
		$testModel = TestControllerMorphsToModel::where('title',  $input['title'])->first();
		$this->assertCount(2, $testModel->testControllerMorphManyModels);
		$this->assertEquals($input['testControllerMorphManyModels'][0]['title'], $testModel->testControllerMorphManyModels->first()->title);
	}

	public function test_can_update_has_many_models_when_updating_a_model() {
		$testModel = TestControllerBelongsToModel::create([
			'title' => 'Test Model',
		]);
		$testModel->testControllerHasManyModels()->createMany([
			[
				'title'       => 'Test Has Many Created Model 1',
				'description' => 'Test Model Description 1',
			],
			[
				'title'       => 'Test Has Many Created Model 2',
				'description' => 'Test Model Description 2',
			],
		]);
		$input = [
			'title' => 'Test Updated Model',
			'testControllerHasManyModels' => $testModel->testControllerHasManyModels->map(function($hasManyModel) {
				$hasManyModel->title = 'Test Updated Belongs To Model '.$hasManyModel->id;
				$hasManyModel->description = 'Test Updated Description '.$hasManyModel->id;
				return $hasManyModel;
			})->toArray()
		];
		$this->controller->modelClass = TestControllerBelongsToModel::class;
		$this->controller->save(new Request($input), $testModel->fill($input));
		$this->assertDatabaseHas('test_controller_models', [
			'title'       => $input['testControllerHasManyModels'][0]['title'],
			'description' => $input['testControllerHasManyModels'][0]['description'],
		]);
		$this->assertDatabaseHas('test_controller_models', [
			'title'       => $input['testControllerHasManyModels'][1]['title'],
			'description' => $input['testControllerHasManyModels'][1]['description'],
		]);
		$this->assertDatabaseHas('test_controller_belongs_to_models', [
			'title' => $input['title'],
		]);
		$testModel = TestControllerBelongsToModel::where('title',  $input['title'])->first();
		$this->assertEquals($input['testControllerHasManyModels'][0]['title'], $testModel->testControllerHasManyModels->first()->title);
	}

	public function test_can_update_morph_many_models_when_updating_a_model() {
		$testModel = TestControllerMorphsToModel::create([
			'title' => 'Test Model',
		]);
		$testModel->testControllerMorphManyModels()->createMany( [
			[
				'title' => 'Test Has Many Created Model 1',
			],
			[
				'title' => 'Test Has Many Created Model 2',
			],
		] );
		$input = [
			'title' => 'Test Updated Model',
			'testControllerMorphManyModels' => $testModel->testControllerMorphManyModels->map(function($hasManyModel) {
				$hasManyModel->title = 'Test Updated Belongs To Model '.$hasManyModel->id;
				return $hasManyModel;
			})->toArray()
		];
		$this->controller->modelClass = TestControllerMorphsToModel::class;
		$this->controller->save(new Request($input), $testModel->fill($input));
		$this->assertDatabaseHas('test_controller_polymorphic_models', [
			'title'       => $input['testControllerMorphManyModels'][0]['title'],
		]);
		$this->assertDatabaseHas('test_controller_polymorphic_models', [
			'title'       => $input['testControllerMorphManyModels'][1]['title'],
		]);
		$this->assertDatabaseHas('test_controller_morphs_to_models', [
			'title' => $input['title'],
		]);
		$testModel = TestControllerMorphsToModel::where('title',  $input['title'])->first();
		$this->assertEquals($input['testControllerMorphManyModels'][0]['title'], $testModel->testControllerMorphManyModels->first()->title);
	}

	public function test_can_redirect_to_specified_route_after_delete() {
		$model = TestControllerModel::create(['title' => 'Test Delete Model']);
		$response = $this->delete('test/controller/model/'.$model->id, ['redirectRoute' => 'test-redirect.index'], ['X-Inertia' => 1]);
		$response->assertRedirect(route('test-redirect.index'));
	}

	public function test_can_redirect_to_specified_route_after_delete_with_route_parameters() {
		$model = TestControllerModel::create(['title' => 'Test Delete Model']);
		$redirectModel = TestControllerModel::create(['title' => 'Test Redirect Model']);
		$response = $this->followingRedirects()->delete('test/controller/model/'.$model->id, ['redirectRoute' => 'test-redirect.index', 'redirectParameters' => ['model' => $redirectModel]], ['X-Inertia' => 1]);
		$response->assertSeeText($redirectModel->title);
	}

	public function test_can_return_a_list_of_records() {
		$response = $this->controller->list(new Request());
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(5, $data->records);
	}

	public function test_can_return_a_list_of_records_with_custom_model_query() {
		/** @var TestControllerModel $model */
		$model = TestControllerModel::where('title', 'Test5')->first();
		$this->controller->modelQuery = $model->testControllerBelongsToModel()->getQuery();
		$response = $this->controller->list(new Request());
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(1, $data->records);
	}

	public function test_can_return_a_list_of_records_with_a_limit() {
		$response = $this->controller->list(new Request(['limit' => 2]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(2, $data->records);
	}

	public function test_can_return_a_list_of_records_with_an_offset_and_limit() {
		$response = $this->controller->list(new Request(['offset' => 2, 'limit' => 2]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertEquals('Test3', $data->records[0]->title);
		$this->assertEquals('Test4', $data->records[1]->title);
		$this->assertFalse(isset($data->records[2]));
	}

	public function test_can_return_a_list_of_records_filtered_with_a_search() {
		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'title',
					'value' => 'Test3',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertEquals('Test3', $data->records[0]->title);
	}

	public function test_can_return_a_list_of_records_filtered_with_an_in_operator_search() {
		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column'   => 'title',
					'operator' => 'in',
					'value'    => ['Test1', 'Test3'],
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(2, $data->records);
		$titleResults = collect($data->records)->pluck('title')->toArray();
		$this->assertContains('Test1', $titleResults);
		$this->assertContains('Test3', $titleResults);
	}

	public function test_can_return_a_list_of_records_filtered_with_a_between_operator_search() {
		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column'   => 'title',
					'operator' => 'between',
					'value'    => ['Test1', 'Test3'],
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(3, $data->records);
		$titleResults = collect($data->records)->pluck('title')->toArray();
		$this->assertContains('Test1', $titleResults);
		$this->assertContains('Test2', $titleResults);
		$this->assertContains('Test3', $titleResults);
	}

	public function test_can_return_a_list_of_records_filtered_with_an_in_operator_relation_search() {
		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column'   => 'testControllerBelongsToModel.title',
					'operator' => 'in',
					'value'    => ['Test Related Model'],
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertEquals('Test5', $data->records[0]->title);
	}

	public function test_can_return_a_list_of_records_filtered_with_a_multi_column_search() {
		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'title',
					'value' => 'Test3',
				],
				[
					'column' => 'is_active',
					'value' => true,
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertEquals('Test3', $data->records[0]->title);

		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'title',
					'value' => 'Test3',
				],
				[
					'logic' => 'or',
					'column' => 'title',
					'value' => 'Test4',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(2, $data->records);

		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'title',
					'value' => 'Test3',
				],
				[
					'logic' => 'or',
					'subSearch' => [
						[
							'column' => 'title',
							'value' => 'Test4',
						],
						[
							'column' => 'is_active',
							'value' => true,
						],
					],
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(2, $data->records);
	}

	public function test_can_return_a_list_of_records_filtered_with_a_relation_search() {
		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'testControllerBelongsToModel.title',
					'value' => 'Test Related Model',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertEquals('Test5', $data->records[0]->title);
	}

	public function test_can_return_a_list_of_records_filtered_with_a_starts_with_search() {
		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'title',
					'operator' => 'startsWith',
					'value' => 'Test',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(5, $data->records);
	}

	public function test_can_return_a_list_of_records_filtered_with_an_ends_with_search() {
		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'description',
					'operator' => 'endsWith',
					'value' => 'model',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(3, $data->records);
	}

	public function test_can_return_a_list_of_records_filtered_with_a_contains_search() {
		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'description',
					'operator' => 'contains',
					'value' => 'description',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(3, $data->records);
	}

	public function test_can_return_a_list_of_records_filtered_with_a_raw_value_search() {
		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'description',
					'operator' => 'like',
					'value' => "'Test description% model'",
					'type' => 'raw',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(3, $data->records);
	}

	public function test_can_return_a_list_of_records_filtered_with_a_date_string_search() {
		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'active_on',
					'value' => 'Jan 1, 2022',
					'type' => 'date',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertEquals('2022-01-01', $data->records[0]->active_on);

		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'active_on',
					'operator' => '>=',
					'value' => 'Jan 1, 2022',
					'type' => 'date',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(5, $data->records);
	}

	public function test_can_return_a_list_of_records_filtered_with_a_between_date_search() {
		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'active_on',
					'operator' => 'between',
					'value' => ['Mar 1, 2022', 'Mar 3, 2022'],
					'type' => 'date',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(3, $data->records);

		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'active_on',
					'operator' => 'between',
					'value' => ['2022-01-01', '2022-01-02'],
					'type' => 'date',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(2, $data->records);
	}

	public function test_can_return_a_list_of_records_filtered_with_a_bool_value_search() {
		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'is_active',
					'value' => 'yes',
					'type' => 'bool',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(3, $data->records);

		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'is_active',
					'value' => 'true',
					'type' => 'bool',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(3, $data->records);

		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'is_active',
					'value' => true,
					'type' => 'bool',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(3, $data->records);

		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'is_active',
					'value' => '1',
					'type' => 'bool',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(3, $data->records);

		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'is_active',
					'operator' => '!=',
					'value' => 'yes',
					'type' => 'bool',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(2, $data->records);

		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'is_active',
					'operator' => '!=',
					'value' => 'true',
					'type' => 'bool',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(2, $data->records);

		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'is_active',
					'operator' => '!=',
					'value' => true,
					'type' => 'bool',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(2, $data->records);

		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'is_active',
					'operator' => '!=',
					'value' => '1',
					'type' => 'bool',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(2, $data->records);

		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'is_active',
					'value' => 'no',
					'type' => 'bool',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(2, $data->records);

		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'is_active',
					'value' => 'false',
					'type' => 'bool',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(2, $data->records);

		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'is_active',
					'value' => false,
					'type' => 'bool',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(2, $data->records);

		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'is_active',
					'value' => '0',
					'type' => 'bool',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(2, $data->records);

		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'is_active',
					'operator' => '!=',
					'value' => 'no',
					'type' => 'bool',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(3, $data->records);

		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'is_active',
					'operator' => '!=',
					'value' => 'false',
					'type' => 'bool',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(3, $data->records);

		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'is_active',
					'operator' => '!=',
					'value' => false,
					'type' => 'bool',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(3, $data->records);

		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'is_active',
					'operator' => '!=',
					'value' => '0',
					'type' => 'bool',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(3, $data->records);

		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'is_active',
					'value' => true,
					'type' => 'boolean',
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(3, $data->records);

		$response = $this->controller->list(new Request([
			'search' => [
				[
					'column' => 'is_active',
					'value' => true,
				],
			]
		]));
		$data = $response->getData();
		$this->assertTrue($data->success);
		$this->assertCount(3, $data->records);
	}
}

class TestModelController extends ModelController {
	public function destroy(Request $request, TestControllerModel $model) {
		return $this->remove($request, $model);
	}
}

class TestControllerModel extends TranquilModel {
	public $timestamps = false;
	protected $fillable = ['title', 'description', 'is_active', 'active_on', 'test_controller_belongs_to_model_id'];
	public function testControllerBelongsToModel() {
		return $this->belongsTo(TestControllerBelongsToModel::class);
	}
	public function testControllerBelongsToManyModels() {
		return $this->belongsToMany(TestControllerBelongsToModel::class, 'test_controller_belongs_to_model_test_controller_model');
	}
}

class TestControllerBelongsToModel extends TranquilModel {
	public $timestamps = false;
	protected $fillable = ['title'];
	public function testControllerHasOneModel() {
		return $this->hasOne(TestControllerModel::class, 'test_controller_belongs_to_model_id');
	}
	public function testControllerHasManyModels() {
		return $this->hasMany(TestControllerModel::class, 'test_controller_belongs_to_model_id');
	}
}

class TestControllerPolymorphicModel extends TranquilModel {
	public $timestamps = false;
	public function morphable() {
		return $this->morphTo();
	}
}

class TestControllerMorphsToModel extends TranquilModel {
	public $timestamps = false;
	protected $fillable = ['title'];
	public function testControllerMorphOneModel() {
		return $this->morphOne(TestControllerPolymorphicModel::class, 'morphable');
	}
	public function testControllerMorphManyModels() {
		return $this->morphMany(TestControllerPolymorphicModel::class, 'morphable');
	}
}

class TestTagModel extends TranquilModel {
	public $timestamps = false;
	protected $fillable = ['title'];

	public function testPostModels() {
		return $this->morphedByMany( TestPostModel::class, 'taggable' );
	}
}
class TestPostModel extends TranquilModel {
	public $timestamps = false;
	protected $fillable = ['title'];
	public function testTagModels() {
		return $this->morphToMany( TestTagModel::class, 'taggable' );
	}
}
