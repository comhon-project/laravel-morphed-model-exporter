# Laravel Morphed Model Exporter

[![Latest Version on Packagist](https://img.shields.io/packagist/v/comhon-project/laravel-morphed-model-exporter.svg?style=flat-square)](https://packagist.org/packages/comhon-project/laravel-morphed-model-exporter)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/comhon-project/laravel-morphed-model-exporter/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/comhon-project/laravel-morphed-model-exporter/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/comhon-project/laravel-morphed-model-exporter/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/comhon-project/laravel-morphed-model-exporter/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/comhon-project/laravel-morphed-model-exporter.svg?style=flat-square)](https://packagist.org/packages/comhon-project/laravel-morphed-model-exporter)

This library allows exporting morphed models (typically via an API). A morphed model is one that is loaded through a `MorphTo` relationship. Since these models belong to different classes, loading them from a collection along with their dependencies and exporting them can be cumbersome. This library makes it easy!

## Installation

You can install the package via composer:

```bash
composer require comhon-project/laravel-morphed-model-exporter
```

## Usage

### Register exporters

In order to be able to export morphed models, you must define morphed model exporters.

To do so, you must define a class with an `__invoke()` method that will return an array of exporters. Each key must be a eloquent model class and each value must be an array. Each array value must/may contain:

-   the key `model_exporter` (required). The associated value must be either a Closure and return the exported model (the eloquent model is inject as parameter) either a JsonResource class.
-   the key `query_builder` (optional). The associated value must be a Closure that will build the query given in parameter.

```php
class MyMorphedModelsExporters
{
    public function __construct(private array $exporters) {}

    public function __invoke()
    {
        return [
            FirstModel::class => [
                'query_builder' => fn ($query) => $query->with('dependency')->select('id', 'dependency_id'),
                'model_exporter' => fn ($model) => $model->toArray(),
            ],
            SecondModel::class => [
                'model_exporter' => SecondModel::class,
            ],
        ]
    }
}
```

Then you will have to register it in your `AppServiceProvider` like this :

```php
    public function register(): void
    {
        $this->app->bind('morphed-model-exporters', MyMorphedModelsExporters::class);
    }
```

### Load morphed models

You should typically load morphed models in Controllers :

```php
use Comhon\MorphedModelExporter\Facades\MorphedModelExporter;

MorphedModelExporter::loadMorphedModels($myModels, 'myMorphToRelation');
```

You can use additional parameters to load differents data according a certain context :

```php
class MyMorphedModelsExporters
{
    public function __construct(private array $exporters) {}

    public function __invoke()
    {
        return [
            FirstModel::class => [
                'query_builder' => fn ($query, array $additionalColumns = []) => $query->select([
                    'id',
                    'dependency_id',
                    ...$additionalColumns
                ]),
            ],
        ]
    }
}
```

```php
use Comhon\MorphedModelExporter\Facades\MorphedModelExporter;

MorphedModelExporter::loadMorphedModels($myModels, 'myMorphToRelation', ['my_column']);
```

### Export morphed models

You should typically export morphed models in API resources :

```php
use Comhon\MorphedModelExporter\Facades\MorphedModelExporter;

'my_morph_to_relation' => $this->whenLoaded(
    'myMorphToRelation',
    fn ($morphedModel) => MorphedModelExporter::exportModel($morphedModel)
),
```

You can use additional parameters to export differents properties according a certain context :

```php
class MyMorphedModelsExporters
{
    public function __construct(private array $exporters) {}

    public function __invoke()
    {
        return [
            FirstModel::class => [
                'model_exporter' => fn ($model, $private = false) => $private
                    ? ['id' => $model->id, 'private' => $model->private]
                    : ['id' => $model->id],
            ],
        ]
    }
}
```

```php
use Comhon\MorphedModelExporter\Facades\MorphedModelExporter;

$private = true;
MorphedModelExporter::exportModel($morphedModel, $private);
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

-   [jean-philippe](https://github.com/comhon-project)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
