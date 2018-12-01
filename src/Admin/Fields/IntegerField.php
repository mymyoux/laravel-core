<?php
namespace Core\Admin\Fields;


class IntegerField extends \Core\Admin\Field
{
    public function __construct()
    {
        parent::__construct('integer');
    }

    public function search($query, $search)
    {
        if(!is_numeric($search))
        {
            return $query;
        }
        return $query->orWhere($this->column, '=', (int)$search);
    }
}