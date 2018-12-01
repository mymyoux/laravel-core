<?php
namespace Core\Admin\Fields;


class HasManyField extends \Core\Admin\Field
{
    protected $_model = NULL;
    public $resource = NULL;
    protected $_fields = NULL;

    public function __construct()
    {
        parent::__construct('hasMany');
        $this->_fields = collect([]);
    }
    public function model($model, $resource = NULL)
    {
        $this->_model = $model;
        $this->resource = $resource??strtolower(last(explode('\\', $model)));
        return $this;
    }
    public function addFields($fields)
    {
        $this->_fields = is_array($fields)?collect($fields):$fields;
        $this->_fields->each(function($item) 
        {
            $item->pivot = $this->column;
        });
        return $this;
    }
    public function hasFields()
    {
        return !$this->_fields->isEmpty();
    }
    public function getFields()
    {
        return $this->_fields;
    }
    public function getModel()
    {
        return $this->_model;
    }
    public function numeric($numeric = True)
    {
        return $this;
    }
    public function min($min)
    {
        return $this;
    }
    public function max($max)
    {
        return $this;
    }
    public function search($query, $search)
    {
        //TODO:handle hasMany Search
        return $query;
    }
}