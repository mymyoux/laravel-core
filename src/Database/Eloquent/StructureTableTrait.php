<?php
namespace Core\Database\Eloquent;

trait StructureTableTrait
{
	private function getColumns()
    {
        return array_keys($this->casts);
    }
	private function getColumnType($name)
    {
        if(isset($this->casts[$name]))
        {
            return $this->casts[$name];
        }
        return NULL;
    }
	private function hasColumn($name)
    {
    	return isset($this->casts[$name]);
    }
    private function isPrimary(...$columns)
    {
        $index = $this->getIndex(...$columns);
        if($index == NULL)
            return False;
        return $index->primary;
    }
    private function isUnique(...$columns)
    {
        $index = $this->getIndex(...$columns);
        if($index == NULL)
            return False;
        return $index->unique;
    }
    private function getIndex(...$columns)
    {
        sort($columns);
        $name = join(',', $columns);
        if(!isset($this->indexes[$name]))
        {
            return NULL;
        }
        return std($this->indexes[$name]);
    }
    public function __call($name, $params)
    {
        return $this->$name(...$params);
    }
}