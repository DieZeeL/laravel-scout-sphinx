<?php

namespace DieZeeL\SphinxScout;

use DieZeeL\SphinxScout\Engines\MySQLSimpleEngine;
use DieZeeL\SphinxScout\Engines\SphinxEngine;
use DieZeeL\SphinxScout\Entities\FieldEntity;
use Foolz\SphinxQL\Drivers\Pdo\Connection;
use Foolz\SphinxQL\SphinxQL;
use Illuminate\Support\ServiceProvider as Provider;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Builder;

class ServiceProvider extends Provider
{
    public function boot()
    {
        resolve(EngineManager::class)->extend('sphinxsearch', function ($app) {
            $options = config('scout.sphinxsearch');
            if (empty($options['socket']))
                unset($options['socket']);
            $connection = new Connection();
            $connection->setParams($options);

            return new SphinxEngine(new SphinxQL($connection));
        });

        resolve(EngineManager::class)->extend('mysqlsimple', function ($app) {
            return new MySQLSimpleEngine();
        });

        $this->app->bind('FieldEntity', FieldEntity::class);

        $this->registerMacro();
    }

    public function registerMacro()
    {
        Builder::macro('where', function (string $attribute, $operator = null, $value = null) {
            if (func_num_args() < 3) {
                $value = $operator;
                $operator = "=";
                if (is_array($value))
                    $operator = "IN";
            }
            $this->wheres[] = [$attribute => [$operator, $value]];
            return $this;
        });

        Builder::macro('count', function () {
            return $this->engine->getTotalCount(
                $this->engine()->search($this)
            );
        });

        Builder::macro('filter', function ($filters) {
            return $this->engine()->addFilters($filters);
        });
    }
}
