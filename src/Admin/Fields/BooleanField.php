<?php
namespace Core\Admin\Fields;


class BooleanField extends \Core\Admin\Field
{
    public function __construct()
    {
        parent::__construct('boolean');
    }
    //ignore
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
    public function cast($value)
    {
        if(!is_string($value))
        {
            return !!$value;
        }
        $value = mb_strtolower($value);
        if($value == 'false')
        {
            return False;
        }
        if($value == 'true')
        {
            return True;
        }
        return !!$value;
    }
    public function search($query, $search)
    {
        return $query->orWhere($this->column, '=', !!$search);
    }
}