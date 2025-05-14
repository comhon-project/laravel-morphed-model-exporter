<?php

namespace Comhon\MorphedModelExporter\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void registerExporters(array $exporters)
 * @method static bool hasExporters()
 * @method static bool hasModelExporter(string $modelClass)
 * @method static \Closure|null getModelExporter(string $modelClass)
 * @method static mixed exportModel(\Illuminate\Database\Eloquent\Model $model)
 * @method static \Illuminate\Support\Collection loadMorphedModels(\Illuminate\Support\Collection $models, string $morphToRelation)
 * @method static \Illuminate\Database\Eloquent\Builder buildQuery(string $modelClass, array|Collection $ids)
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
