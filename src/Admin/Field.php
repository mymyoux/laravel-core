<?php
namespace Core\Admin;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Illuminate\Contracts\Support\Arrayable;
abstract class Field implements Arrayable, JsonSerializable, Jsonable
{
    public $sortable = False;
    public $orderable = False;
    public $nullable = False;
    public $editable = True;
    public $required = False;
    public $searchable = False;
    public $listable = False;
    public $viewable = False;
    public $pivot = NULL;
    public $label;
    public $column;
    public $type;
    public $validators = [];

    public function __construct($type)
    {
        $this->type = $type;
    }
    public function sortable($value = True)
    {
        $this->sortable = $value;
        return $this;
    }
    public function viewable($value = True)
    {
        $this->viewable = $value;
        return $this;
    }
    public function listable($value = True)
    {
        $this->listable = $value;
        return $this;
    }
    public function searchable($value = True)
    {
        $this->searchable = $value;
        return $this;
    }
    public function orderable($value = True)
    {
        $this->orderable = $value;
        return $this;
    }
    public function nullable($value = True)
    {
        $this->nullable = $value;
        return $this;
    }
    public function required($value = True)
    {
        $this->required = $value;
        return $this;
    }
    public function editable($value = True)
    {
        $this->editable = $value;
        return $this;
    }
    public function pivot($value = NULL)
    {
        $this->pivot = $value;
        return $this;
    }
    public function validators($validators)
    {
        $this->validators = $validators;
        return $this;
    }
    public function validator($validator)
    {
        if(is_string($validator))
        {
            $this->validators[$validator] = True;
        }else
        {
            $this->validators = array_merge($this->validators, $validator);
        }
        return $this;
    }
    public function numeric($numeric = True)
    {
        $this->validators['numeric'] = True;
        return $this;
    }
    public function min($min)
    {
        $this->validator(['min'=>$min]);
        return $this;
    }
    public function max($max)
    {
        $this->validator(['max'=>$max]);
        return $this;
    }

    public function cast($value)
    {
        return $value;
    }
    public function search($query, $search)
    {
        return $query->orWhere($this->column, '=', $search);
    }


    protected function make($column, $label = NULL)
    {
        if(!isset($label))
        {
            $label = studly_case($column);
        }
        $this->label = $label;
        $this->column = $column;
        return $this;
    }
    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }
    public function toArray()
    {
        $data = [];
        foreach($this as $key=>$value)
        {
            if(starts_with($key, '_'))
                continue;
            $data[$key] = $value;
        }
        return $data;
    }
    /**
     * Handle dynamic static method calls into the method.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return (new static)->$method(...$parameters);
    }
}