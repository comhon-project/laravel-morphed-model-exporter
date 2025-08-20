<?php

namespace Comhon\MorphedModelExporter\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void registerExporters(array $exporters)
 * @method static bool hasExporters()
 * @method static bool hasModelExporter(string $modelClass)
 * @method static \Closure|null getModelExporter(string $modelClass)
 * @method static mixed exportModel(\Illuminate\Database\Eloquent\Model $model, ...$params)
 * @method static \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Model loadMorphedModels(\Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Model $models, string $morphToRelation, ...$params)
 * @method static \Illuminate\Database\Eloquent\Builder buildQuery(string $modelClass, array|Collection $ids, ...$params)
 *
 * @see Comhon\MorphedModelExporter\MorphedModelExporter
 */
class MorphedModelExporter extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Comhon\MorphedModelExporter\MorphedModelExporter::class;
    }
}
