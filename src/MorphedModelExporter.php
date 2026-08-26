<?php

namespace Comhon\MorphedModelExporter;

use Comhon\MorphedModelExporter\Exceptions\MorphedModelExporterException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class MorphedModelExporter
{
    const QUERY_BUILDER = 'query_builder';

    const MODEL_EXPORTER = 'model_exporter';

    private array $exporters = [];

    private array $modelExporters = [];

    public function __construct()
    {
        if (app()->bound('morphed-model-exporters')) {
            $this->exporters = app('morphed-model-exporters')();
        }
    }

    public function registerExporters(array $exporters)
    {
        $this->exporters = $exporters;
    }

    public function hasExporters()
    {
        return ! empty($this->exporters);
    }

    public function hasModelExporter(string $modelClass)
    {
        return $this->getModelExporter($modelClass) !== null;
    }

    private function buildModelExporter(string $modelClass): ?\Closure
    {
        if (! array_key_exists($modelClass, $this->exporters)) {
            return null;
        }

        $modelExporter = $this->exporters[$modelClass][self::MODEL_EXPORTER] ?? null;
        if (! isset($modelExporter)) {
            throw new MorphedModelExporterException('undefined morphed model exporter');
        }
        $isApiResource = is_string($modelExporter) && is_subclass_of($modelExporter, JsonResource::class);
        if (! $isApiResource && ! ($modelExporter instanceof \Closure)) {
            throw new MorphedModelExporterException('invalid morphed model exporter, it must be a Closure or an API resource class');
        }

        return is_string($modelExporter)
            ? fn ($model) => new $modelExporter($model)
            : $modelExporter;
    }

    public function getModelExporter(string $modelClass): ?\Closure
    {
        if (! array_key_exists($modelClass, $this->modelExporters)) {
            $this->modelExporters[$modelClass] = $this->buildModelExporter($modelClass);
        }

        return $this->modelExporters[$modelClass];
    }

    /**
     * Export the model to be exported through an API.
     *
     * Call the model exported associated with the givven model.
     *
     * @param  mixed  ...$params  additional parameters injected when calling model_exporter closure
     */
    public function exportModel(?Model $model, ...$params): mixed
    {
        if (! $model) {
            return null;
        }

        $exporter = $this->getModelExporter(get_class($model));

        return $exporter ? $exporter($model, ...$params) : throw new MorphedModelExporterException('exporter not defined');
    }

    /**
     * Loads the given relationship for each models in the given collection.
     *
     * Only models for which an exporter is defined will be loaded; models with
     * a null morph type are marked as loaded (null).
     *
     * @param  mixed  ...$params  additional parameters injected when calling query_builder closure
     */
    public function loadMorphedModels(Collection|Model $models, string $morphToRelation, ...$params): Collection|Model
    {
        $collection = new EloquentCollection($models instanceof Model ? [$models] : $models);

        $collection = $collection->whereNotNull();
        if ($collection->isEmpty() || ! $this->hasExporters()) {
            return $models;
        }

        try {
            $relation = $collection->first()->$morphToRelation();
            if (! $relation instanceof MorphTo) {
                throw new \Exception;
            }
        } catch (\Throwable $th) {
            throw new MorphedModelExporterException("invalid relationship '$morphToRelation', it must be a MorphTo relationship");
        }

        $morphType = $relation->getMorphType();

        $loadables = $collection->filter(function ($model) use ($morphType) {
            $type = $model->$morphType;

            return ! $type || $this->hasModelExporter(Relation::getMorphedModel($type) ?? $type);
        });
        if ($loadables->isEmpty()) {
            return $models;
        }

        $constraints = [];
        foreach ($loadables->pluck($morphType)->unique() as $type) {
            if (! $type) {
                continue;
            }
            $class = Relation::getMorphedModel($type) ?? $type;
            $builder = $this->exporters[$class][self::QUERY_BUILDER] ?? null;
            // a type without constraint is still loaded, just without a customized query
            if (! isset($builder)) {
                continue;
            }
            if (! ($builder instanceof \Closure)) {
                throw new MorphedModelExporterException('invalid query builder, it must be a Closure');
            }
            $constraints[$class] = fn (Builder $query) => $builder($query, ...$params);
        }

        $loadables->load([
            $morphToRelation => fn (MorphTo $morphTo) => $morphTo->constrain($constraints),
        ]);

        return $models;
    }
}
