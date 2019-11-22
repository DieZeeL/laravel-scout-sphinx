<?php


namespace DieZeeL\SphinxScout\Classes;

use Carbon\Carbon;
use Composer\Package\Package;
use DieZeeL\SphinxScout\Entities\FieldEntity;
use Foolz\SphinxQL\SphinxQL;
use Illuminate\Support\Arr;
use Laravel\Scout\Builder as ScoutBuilder;

class Builder extends ScoutBuilder
{
    //private $builder;

    /**
     * @var Attributes
     */
    public $attributes;

    public $matches = [];

    public $casts = [];

    public $withes = [];

    /**
     * Create a new search builder instance.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $query
     * @param \Closure $callback
     * @param bool $softDelete
     * @return void
     */
    public function __construct($model, $query, $callback = null, $softDelete = false)
    {

        $this->attributes = $model->getSearchableAttributes();
        $this->casts = collect($model->searchableCasts());
        $query = $this->prepareQuery($query);
        parent::__construct($model, $query, $callback, false);
        if ($softDelete) {
            $this->wheres[] = ['__soft_deleted' => ['=', 0]];
        }
        //$this->builder = new ScoutBuilder($model, $query, $callback, $softDelete);
    }

    public function where($attribute, $operator)
    {
        list($attribute, $operator, $value) = func_get_args();
        if (func_num_args() < 3) {
            $value = $operator;
            $operator = "=";
        }

        switch ($this->casts->get($attribute, false)) {
            case 'timestamp':
                $this->wheres[] = [$attribute => [$operator, strtotime((string)$value)]];
                break;
            case 'mva':
                if (is_array($value) && $operator != "IN") {
                    foreach ($value as $v) {
                        $this->wheres[] = [$attribute => [$operator, (int)$v]];
                    }
                } else {
                    $this->wheres[] = [$attribute => [$operator, (int)$value]];
                }
                break;
            case 'uint':
                if (is_array($value) && $operator != "IN") {
                    $this->wheres[] = [$attribute => ["IN", array_map('intval', $value)]];
                } else {
                    $this->wheres[] = [$attribute => [$operator, (int)$value]];
                }
                break;
            default:
                if (is_array($value) && $operator != "IN") {
                    $this->wheres[] = [$attribute => ["IN", $value]];
                } else {
                    $this->wheres[] = [$attribute => [$operator, $value]];
                }
        }
        return $this;
    }

    public function match(string $attribute, $value)
    {
        $this->matches[$attribute] = SphinxQL::expr('"*' . $value . '*"/1');
        //$this->matches[$attribute] = '*'.$value.'*';
    }

    public function filter(array $filters)
    {
        foreach ($filters as $field => $value) {
            if ($filter = $this->attributes->filters->get($field, false)) {
                /** @var FieldEntity $filter */
                $this->where($filter->field, $filter->expr, $value);
            }
        }
        return $this;
    }

//    public function count()
//    {
//        return $this->engine->getTotalCount(
//            $this->engine()->search($this)
//        );
//    }

    protected function prepareQuery(string $search = null)
    {
        if (is_null($search))
            return $search;

        if (preg_match_all('/(?P<fields>([\w-]+\:\([^\)]+\))|([\w-]+:[\w-]+))/iu', $search, $matches)) {
            $fields = array_filter($matches['fields']);
            $search = str_replace($fields, '', $search);
            foreach ($fields as $field) {
                if (preg_match('/^(?P<field>[\w-]+)\:\(?(?P<value>[^\)]+)\)?/iu', $field, $match)) {
                    if ($attr = $this->attributes->searches->get($match['field'], false)) {
                        $this->match($attr->field, $match['value']);
                    }
                    if ($attr = $this->attributes->filters->get($match['field'], false)) {
                        $this->where($attr->field, $attr->expr, $match['value']);
                    }
                }
            }
        }
        if ($attr = $this->attributes->searches->get('tags', false)) {
            if (preg_match_all('/(?P<tags>(\#\([^\)]+\))|(\#[\w-]+))/iu', $search, $matches)) {
                $tags = array_filter($matches['tags']);
                $search = str_replace($tags, '', $search);
                foreach ($tags as &$tag) {
                    if (preg_match('/^\#\(?(?P<value>[^\)]+)\)?/iu', $tag, $match)) {
                        $tag = $match['value'];
                    }
                    $this->match('tags', $tag);
                }
            }
        }
        $search = preg_replace('/\s{2,}/iu', ' ', $search);
        return trim($search);
    }

    /**
     * Include soft deleted records in the results.
     *
     * @return \Laravel\Scout\Builder
     */
    public function withTrashed()
    {
        foreach ($this->wheres as $key => $where){
            if(Arr::exists($where,'__soft_deleted')){
                unset($this->wheres[$key]);
            }
        }
        return $this;
    }

    /**
     * Include only soft deleted records in the results.
     *
     * @return $this
     */
    public function onlyTrashed()
    {
        return tap($this->withTrashed(), function () {
            $this->wheres[] = ['__soft_deleted' => ['=', 1]];
        });
    }

    public function with($with){
        $this->withes = is_array($with) ? $with : func_get_args();
        return $this;
    }
}
