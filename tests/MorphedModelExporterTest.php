<?php

namespace Tests;

use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Todo;
use App\Models\TrainingSession;
use Comhon\MorphedModelExporter\Facades\MorphedModelExporter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Assert as PHPUnit;

class MorphedModelExporterTest extends TestCase
{
    use RefreshDatabase;

    private function registerShedulableExporters(array $exporters)
    {
        app()->bind('morphed-model-exporters', function () use ($exporters) {
            return new class($exporters)
            {
                public function __construct(private array $exporters) {}

                public function __invoke()
                {
                    return $this->exporters;
                }
            };
        });
    }

    public function test_load_morphed_model_with_exporter_success()
    {
        $this->registerShedulableExporters([
            TrainingSession::class => [
                'query_builder' => fn ($query) => $query->with('program:id,name')->select('id', 'training_program_id'),
                'model_exporter' => fn ($model) => $model,
            ],
            Appointment::class => [
                'model_exporter' => AppointmentResource::class,
            ],
        ]);

        TrainingSession::factory()->has(Todo::factory(), 'todo')->create();
        Appointment::factory()->has(Todo::factory(), 'todo')->create();

        $todos = Todo::all();
        $this->assertCount(2, $todos);

        MorphedModelExporter::loadMorphedModels($todos, 'todoable');

        foreach ($todos as $todo) {
            $this->assertTrue($todo->relationLoaded('todoable'));
        }
    }

    public function test_load_morphed_model_without_exporter_success()
    {
        TrainingSession::factory()->has(Todo::factory(), 'todo')->create();
        Appointment::factory()->has(Todo::factory(), 'todo')->create();

        $todos = Todo::all();
        $this->assertCount(2, $todos);

        MorphedModelExporter::loadMorphedModels($todos, 'todoable');

        foreach ($todos as $todo) {
            $this->assertFalse($todo->relationLoaded('todoable'));
        }
    }

    public function test_load_morphed_model_with_unused_exporter_success()
    {
        $this->registerShedulableExporters([
            Appointment::class => [
                'model_exporter' => AppointmentResource::class,
            ],
        ]);

        TrainingSession::factory()->has(Todo::factory(), 'todo')->create();
        Todo::factory()->create();

        $todos = Todo::all();
        $this->assertCount(2, $todos);

        MorphedModelExporter::loadMorphedModels($todos, 'todoable');

        foreach ($todos as $todo) {
            $this->assertFalse($todo->relationLoaded('todoable'));
        }
    }

    public function test_export_morphed_models()
    {
        $this->registerShedulableExporters([
            TrainingSession::class => [
                'query_builder' => fn ($query) => $query->with('program:id,name')->select('id', 'training_program_id'),
                'model_exporter' => fn ($model) => $model,
            ],
            Appointment::class => [
                'model_exporter' => AppointmentResource::class,
            ],
        ]);

        $trainingSession = TrainingSession::factory()->has(Todo::factory(), 'todo')->create();
        $appointment = Appointment::factory()->has(Todo::factory(), 'todo')->create();

        $todos = Todo::orderBy('id')->get();
        $this->assertCount(2, $todos);

        MorphedModelExporter::loadMorphedModels($todos, 'todoable');

        $exported = MorphedModelExporter::exportModel($todos[0]->todoable);
        $this->assertInstanceOf(Model::class, $exported);
        PHPUnit::assertEquals([
            'id' => $trainingSession->id,
            'training_program_id' => $trainingSession->training_program_id,
            'program' => [
                'id' => $trainingSession->program->id,
                'name' => $trainingSession->program->name,
            ],
        ], $exported->toArray());

        $exported = MorphedModelExporter::exportModel($todos[1]->todoable);
        $this->assertInstanceOf(AppointmentResource::class, $exported);
        $this->assertEquals([
            'id' => $appointment->id,
            'created_at' => $appointment->created_at,
        ], $exported->toArray(null));
    }

    public function test_get_exporter_undefined()
    {
        MorphedModelExporter::registerExporters([
            Appointment::class => null,
        ]);

        $this->expectExceptionMessage('undefined morphed model exporter');
        MorphedModelExporter::getModelExporter(Appointment::class);
    }

    public function test_get_exporter_invalid()
    {
        MorphedModelExporter::registerExporters([
            Appointment::class => [
                'model_exporter' => 'foo',
            ],
        ]);

        $this->expectExceptionMessage('invalid morphed model exporter, it must be a Closure or an API resource class');
        MorphedModelExporter::getModelExporter(Appointment::class);
    }

    public function test_build_query_invalid()
    {
        MorphedModelExporter::registerExporters([
            Appointment::class => [
                'model_exporter' => AppointmentResource::class,
                'query_builder' => 'foo',
            ],
        ]);

        $this->expectExceptionMessage('invalid query builder, it must be a Closure');
        MorphedModelExporter::buildQuery(Appointment::class, []);
    }

    public function test_load_morph_model_invalid_relation_not_morph_to()
    {
        MorphedModelExporter::registerExporters([
            Appointment::class => [
                'model_exporter' => AppointmentResource::class,
            ],
        ]);

        $this->expectExceptionMessage("invalid relationship 'creator', it must be a MorphTo relationship");
        MorphedModelExporter::loadMorphedModels(collect([Todo::factory()->create()]), 'creator');
    }

    public function test_load_morph_model_invalid_relation_doesnt_exist()
    {
        MorphedModelExporter::registerExporters([
            Appointment::class => [
                'model_exporter' => AppointmentResource::class,
            ],
        ]);

        $this->expectExceptionMessage("invalid relationship 'foo', it must be a MorphTo relationship");
        MorphedModelExporter::loadMorphedModels(collect([Todo::factory()->create()]), 'foo');
    }
}
