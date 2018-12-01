<?php
namespace Core\Admin\Fields;


class BelongsToField extends \Core\Admin\Field
{
    protected $_model = NULL;
    public $resource = NULL;

    public function __construct()
    {
        parent::__construct('belongsTo');
    }
    public function model($model, $resource = NULL)
    {
        $this->_model = $model;
        $this->resource = $resource??strtolower(last(explode('\\', $model)));
        return $this;
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
        //TODO:handle belongsTo Search
        return $query;
    }
}