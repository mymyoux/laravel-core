<?php
namespace Core\Database\Eloquent;

class Table
{
    protected static function structure_path()
    {
        return base_path('bootstrap/tables/Structure');
    }
    protected static function structure_namespace()
    {
        return 'Tables\Structure\\';
    }
    public static function sanitize($name)
    {
        return studly_case($name);
    }
    public static function hasTable($name)
    {
        $name = self::sanitize($name);
        $fullname = self::structure_namespace().$name;
        if(class_exists($fullname))
        {
            return True;
        }
        return False;
    }
    public static function getTable($name)
    {
        $name = self::sanitize($name);
        $fullname = self::structure_namespace().$name;
        if(class_exists($fullname))
        {
            return new $fullname;
        }
        return NULL;
    }

}