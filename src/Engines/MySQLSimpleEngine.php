<?php

namespace DieZeeL\SphinxScout\Engines;

use Foolz\SphinxQL\Exception\ConnectionException;
use Foolz\SphinxQL\Exception\DatabaseException;
use Foolz\SphinxQL\Exception\SphinxQLException;
use Foolz\SphinxQL\Helper;
use Foolz\SphinxQL\SphinxQL;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine as AbstractEngine;
use Laravel\Scout\Searchable;

class MySQLSimpleEngine extends AbstractEngine
{

    /**
     * @var SphinxQL
     */
    protected $sphinx;

    /**
     * @var array
     */
    //protected $whereIns = [];

    /**
     * @var array
     */
    protected $wheres = [];

    public function __construct($sphinx)
    {
        $this->sphinx = $sphinx;
    }

    /**
     * Update the given model in the index.
     *
     * @param \Illuminate\Database\Eloquent\Collection $models
     * @return void
     */
    public function update($models)
    {
        if ($models->isEmpty()) {
            return;
        }
        $models->each(function ($model) {
            if (!empty($searchableData = $model->toSearchableArray())) {
                if (isset($model->isRT)) { // Only RT indexes support replace
                    $index = $model->searchableAs();
                    $searchableData['id'] = (int)$model->getKey();
                    $columns = array_keys($searchableData);

                    $sphinxQuery = $this->sphinx
                        ->replace()
                        ->into($index)
                        ->columns($columns)
                        ->values($searchableData);
                    $sphinxQuery->execute();
                }
            }
        });
    }

    /**
     * Remove the given model from the index.
     *
     * @param \Illuminate\Database\Eloquent\Collection $models *
     * @return void
     */
    public function delete($models)
    {
        if ($models->isEmpty()) {
            return;
        }
        $models->each(function ($model) {
            if (isset($model->isRT)) { // Only RT indexes support deletes
                $index = $model->searchableAs();
                $key = $model->getKey();
                $sphinxQuery = $this->sphinx
                    ->delete()
                    ->from($index)
                    ->where('id', '=', $key);
                $sphinxQuery->execute();
            }
        });
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     * @return mixed
     */
    public function search(Builder $builder)
    {
        try {
            $result = $this->performSearch($builder)
                ->execute();
        } catch (DatabaseException| ConnectionException| SphinxQLException $e) {
            if (config('scout.sphinxsearch.fallback')) {
                $result = $this->mySqlSearch($builder);
            } else {
                $result = [];
            }
        }
        return $result;
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     * @param int $perPage
     * @param int $page
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->performSearch($builder)->limit($perPage * ($page - 1), $perPage)
            ->execute();
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param Builder $builder
     * @param mixed $results
     * @param Model|Searchable $model
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function map(Builder $builder, $results, $model)
    {
        if ($results->count() === 0) {
            return $model->newCollection();
        }

        $objectIds = collect($results->fetchAllAssoc())->pluck('id')->values()->all();

        $objectIdPositions = array_flip($objectIds);

        return $model->getScoutModelsByIds(
            $builder, $objectIds
        )->filter(function (/** @var Searchable $model */ $model) use ($objectIds) {
            return in_array($model->getScoutKey(), $objectIds);
        })->sortBy(function (/** @var Searchable $model */ $model) use ($objectIdPositions) {
            return $objectIdPositions[$model->getScoutKey()];
        })->values();
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param mixed $results
     * @return Collection
     */
    public function mapIds($results)
    {
        return collect($results->fetchAllAssoc())->pluck('id')->values();
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param mixed $results
     * @return int
     */
    public function getTotalCount($results)
    {
        $res = (new Helper($this->sphinx->getConnection()))->showMeta()->execute();
        $assoc = $res->fetchAllAssoc();
        $totalCount = $results->count();
        foreach ($assoc as $item => $value) {
            if ($value["Variable_name"] == "total_found") {
                $totalCount = $value["Value"];
            }
        }
        //if ($totalCount >= 1000)
        //    $totalCount = 999;
        return $totalCount;
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param Model $model
     * @return void
     */
    public function flush($model)
    {
        // TODO: Implement flush() method.
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     * @return SphinxQL
     */
    protected function performSearch(Builder $builder)
    {
        /**
         * @var Searchable $model
         */
        $model = $builder->model;
        $index = $model->searchableAs();
        $columns = array_keys($model->toSearchableArray());

        $query = $this->sphinx
            ->select('*')
            ->from($index)
            ->match($columns, SphinxQL::expr('"' . $builder->query . '"/1'));

        foreach ($this->wheres as $clause => $where) {
            $query->where($clause, $where[0], $where[1]);
        }

        //foreach ($this->whereIns as $whereIn) {
        //    $query->where(key($whereIn), 'IN', $whereIn[key($whereIn)]);
        //}

        if ($builder->callback) {
            call_user_func(
                $builder->callback,
                $query
            );
        }

        foreach ($builder->orders as $order) {
            $query->orderBy($order['column'], $order['direction']);
        }

        return $query;
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     * @return SphinxQL
     */
    protected function mySqlSearch(Builder $builder)
    {
        /**
         * @var Searchable $model
         */
        $model = $builder->model;
        //$index = $model->searchableAs();
        $columns = array_keys($model->toSearchableArray());

        $query = $this->sphinx
            ->select('*')
            ->from($index)
            ->match($columns, SphinxQL::expr('"' . $builder->query . '"/1'));

        foreach ($this->wheres as $clause => $where) {
            $query->where($clause, $where[0], $where[1]);
        }

        //foreach ($this->whereIns as $whereIn) {
        //    $query->where(key($whereIn), 'IN', $whereIn[key($whereIn)]);
        //}

        if ($builder->callback) {
            call_user_func(
                $builder->callback,
                $query
            );
        }

        foreach ($builder->orders as $order) {
            $query->orderBy($order['column'], $order['direction']);
        }

        return $query;
    }

    /**
     * @param string $attribute
     * @param array $arrayIn
     */
//    public function addWhereIn(string $attribute, array $arrayIn)
//    {
//        $this->whereIns[] = [$attribute => $arrayIn];
//    }

    /**
     * @param string $attribute
     * @param string|array|null $operator
     * @param string|array|null $value
     */
    public function addWhere(string $attribute, $operator = null, $value = null)
    {
        $this->wheres[] = [$attribute => [$operator, $value]];
    }
}
