<?php
namespace Core\Admin\Fields;


class StringField extends \Core\Admin\Field
{
    public function __construct()
    {
        parent::__construct('string');
    }
    public function search($query, $search)
    {
        return $query->orWhere($this->column,  'LIKE', '%'.$search.'%');
    }
}