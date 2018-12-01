<?php
namespace Core\Admin\Fields;


class PictureField extends \Core\Admin\Field
{
    public function __construct()
    {
        parent::__construct('picture');
    }
    public function search($query, $search)
    {
        return $query;
    }
}