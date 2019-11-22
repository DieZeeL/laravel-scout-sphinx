<?php


namespace DieZeeL\SphinxScout\Classes;


use DieZeeL\SphinxScout\Entities\FieldEntity;
use DieZeeL\SphinxScout\Traits\Searchable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class Attributes
{
    public $model;
    public $array;
    public $sphinx;
    public $filters;
    public $searches;
    public $relations;

    public function __construct($model)
    {
        $this->model = $model;
        if (!in_array(Searchable::class, array_keys((new \ReflectionClass($model))->getTraits())))
            throw new \Exception();
        $this->array = collect();

        foreach ($model->searchableRules() as $key => $field) {
            $this->array->put($key, new FieldEntity($key, $field));
        }
        $this->sphinx = collect();
        $this->filters = collect();
        $this->searches = collect();
        $this->array->each(function ($attr, $key) {
            if (!$this->sphinx->has($attr->name)) {
                $this->sphinx->put($attr->name, $attr->field);
            }
            if (!is_null($attr->expr)) {
                $this->filters->put($attr->name, $attr);
            } else {
                $this->searches->put($attr->name, $attr);
            }
            if (is_string($attr->db)) {
                $segments = explode('.', $attr->db);
                if (count($segments) > 1) {
                    $this->relations[] = implode('.', array_slice($segments, 0, count($segments) - 1));
                    $attr->db = $segments;
                }
                unset($segments);
            }
        });
    }

    public function toSearchableArray()
    {
        $model = $this->model;
        return $this->array->mapWithKeys(function ($attr) {
            $value = $this->getModelValue($attr->db, '');
            if ($value instanceof Carbon) {
                $value = $value->timestamp;
            }
            return [$attr->field => $value];
        })->toArray();
    }

    public function getModelValue($key, $default = null)
    {
        $model = $this->model;

        if ($default instanceof \Closure) {
            $default = call_user_func($default, $model);
        }

        if (is_array($model)) {
            return Arr::get($model, $key, $default);
        }

        if (is_null($key)) {
            return $default;
        }

        if (is_array($key)) {
                foreach ($key as $segment) {
                    try {
                        $model = $model->{$segment};
                    } catch (\Exception $e) {
                        return $default;
                    }
                }
                return $model;
            }

        if ($key instanceof \Closure) {
            $res = call_user_func($key, $model);
            return is_null($res) ? $default : $res;
        }

        if (isset($model->{$key})) {
            return $model->{$key};
        }

        return $default;
    }
}
