<?php

namespace Comhon\MorphedModelExporter;

use Comhon\MorphedModelExporter\Exceptions\MorphedModelExporterException;
use Illuminate\Database\Eloquent\Builder;
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
     * Build query to load morphed models.
     *
     * If it exists, call the query builder associated with the model class.
     *
     * @param  mixed  ...$params  additional parameters injected when calling query_builder closure
     */
    public function buildQuery(string $modelClass, array|Collection $ids, ...$params): Builder
    {
        $query = $modelClass::query()->whereIn((new $modelClass)->getKeyName(), $ids);

        $builder = $this->exporters[$modelClass][self::QUERY_BUILDER] ?? null;
        if (isset($builder)) {
            if (! ($builder instanceof \Closure)) {
                throw new MorphedModelExporterException('invalid query builder, it must be a Closure');
            }
            $builder($query, ...$params);
        }

        return $query;
    }

    /**
     * Loads the given relationship for each models in the given collection.
     *
     * Only models for which an exporter is defined will be loaded.
     *
     * @param  mixed  ...$params  additional parameters injected when calling query_builder closure
     */
    public function loadMorphedModels(Collection|Model $models, string $morphToRelation, ...$params): Collection|Model
    {
        $collection = $models instanceof Model
            ? new Collection([$models])
            : $models;

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

        $foreignIdProperty = $relation->getForeignKeyName();

        $grouped = $collection->groupBy($relation->getMorphType());
        foreach ($grouped as $type => $typeModels) {
            if (! $type) {
                continue;
            }
            $class = Relation::getMorphedModel($type) ?? $type;
            if (! $this->hasModelExporter($class)) {
                continue;
            }
            $query = $this->buildQuery($class, $typeModels->pluck($foreignIdProperty), ...$params);
            $morphedModels = $query->get()->keyBy($query->getModel()->getKeyName());

            foreach ($typeModels as $model) {
                $model->setRelation($morphToRelation, $morphedModels->get($model->$foreignIdProperty));
            }
        }

        return $models;
    }
}
