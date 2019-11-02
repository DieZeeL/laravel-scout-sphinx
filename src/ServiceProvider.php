<?php

namespace DieZeeL\SphinxScout;

use DieZeeL\SphinxScout\Engines\MySQLSimpleEngine;
use DieZeeL\SphinxScout\Engines\SphinxEngine;
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

//        Builder::macro('whereIn', function (string $attribute, array $arrayIn) {
//            $this->engine()->addWhereIn($attribute, $arrayIn);
//            return $this;
//        });
        Builder::macro('where', function (string $attribute, $operator = null, $value = null) {
            $this->engine()->addWhere($attribute, $operator, $value);
            return $this;
        });

        Builder::macro('count', function () {
            return $this->engine->getTotalCount(
                $this->engine()->search($this)
            );
        });
    }
}
