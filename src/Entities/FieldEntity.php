<?php

namespace DieZeeL\SphinxScout\Entities;

class FieldEntity
{
    public $name;
    public $wheres;
    public $field;
    public $expr;
    public $db;

    public function __construct(string $name, $field, $expr = null, $db = null, array $wheres = null)
    {
        if (func_num_args() == 2 && is_array($field)) {
            $this->name = $name;
            $this->field = $field[0];
            $this->expr = isset($field[1]) ? $field[1] : null;
            $this->db = isset($field[2]) ? $field[2] : $field[0];
            $this->wheres = isset($field[3]) ? $field[3] : null;
        } else {
            $this->name = $name;
            $this->field = $field;
            $this->expr = $expr;
            $this->db = $db;
            $this->wheres = $wheres;
        }
    }


//    public function __call($name, $arguments)
//    {
//        $test = "test";
//        // TODO: Implement __call() method.
//    }

    public static function __callStatic($name, $arguments)
    {
        // TODO: Implement __callStatic() method.
    }
}
