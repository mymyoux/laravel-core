<?php
namespace Core\Database\Eloquent\Relations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot as BasePivot;
use Illuminate\Database\Eloquent\Builder;
use Core\Database\Eloquent\Table;
/**
 * Used to allow pivot to only modify one row
 */
class Pivot extends BasePivot
{
        /**
     * Set the keys for a save update query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQuery(Builder $query)
    {
        $query = parent::setKeysForSaveQuery($query);
        $query = $this->customQuery($query);

        return $query;
    }
    protected function getDeleteQuery()
    {
        $query = parent::getDeleteQuery();
        $query = $this->customQuery($query);
        return $query;

    }
    protected function customQuery($query)
    {
        $table = Table::getTable($this->getTable());
        if(isset($table))
        {
            $columns = [$this->foreignKey,$this->relatedKey];
            if(!$table->isUnique(...$columns))
            {
                //not unique
                $query->limit(1);
                $keys = array_keys($this->original);
                //only additional 
                $keys = array_values(array_diff($keys, $columns));
                foreach($keys as $key)
                {
                    $query->where($key, '=', $this->original[$key]);
                }
            }
        }
        return $query;
    }
}