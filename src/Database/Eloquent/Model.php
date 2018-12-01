<?php

namespace Core\Database\Eloquent;

use Illuminate\Database\Eloquent\Model as BaseModel;
use Core\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use DateTime;
use  Core\Database\Eloquent\Relations\Pivot;

abstract class Model extends BaseModel
{
    public function setKey($value)
    {
        return $this->setAttribute($this->getKeyName(), $value);
    }
    public function getDateFormat()
    {
        return 'Y-m-d H:i:s.u';
    }
    public function fromDateTime($value)
    {
        if($value instanceof Expression)
        {
            return $value;
        }
        return parent::fromDateTime($value);
    }

    public function asDateTime( $value )
    {
        if (!($value instanceof Expression) && !strpos($value, '.'))
        {
            // manage case if it's only a date in datebase like 2017-01-01 instead of 2017-01-01 00:00:00
            if (preg_match('/[0-9]{4}\-[0-9]{2}\-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}/', $value))
                $value .= '.000';
        }

        if ($value instanceof Expression)
        {

            if (starts_with($value->getValue(), 'NOW(') || starts_with($value->getValue(), 'CURRENT_TIMESTAMP('))
            {
                error_log('[laravel-core] value:' . $value->getValue() . ' return Carbon::now');
                return \Carbon\Carbon::now();
            }
        }

        if (is_string($value))
        {
            if ($value === '0000-00-00 00:00:00' || $value === '0000-00-00 00:00:00.000')
            {
                error_log('[laravel-core] value:' . $value . ' ' . get_class($this) . ' return Carbon::now');
                return \Carbon\Carbon::now();
            }
        }

        return parent::asDateTime($value);
    }
    /**
     * Unload loaded relations
     * @param string relations names. If none specified all relations will be striped
     * @return self
     */
    public function unload(...$args)
    {
        $args = array_reduce($args, function($args, $item)
        {
            $items = explode(',', $item);
            return array_merge($args, array_map('trim', $items));
        }, []);
        if(empty($args))
        {
            $this->setRelations([]);
            return $this;
        }
        foreach($args as $relation)
        {
            unset($this->relations[$relation]);
        }
        return $this;
    }
   
    /**
     * Create a new pivot model instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  array  $attributes
     * @param  string  $table
     * @param  bool  $exists
     * @param  string|null  $using
     * @return \Illuminate\Database\Eloquent\Relations\Pivot
     */
    public function newPivot(BaseModel $parent, array $attributes, $table, $exists, $using = null)
    {
        return $using ? $using::fromRawAttributes($parent, $attributes, $table, $exists)
                      : Pivot::fromAttributes($parent, $attributes, $table, $exists);
    }
}
