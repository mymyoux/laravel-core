<?php
namespace Core\Traits;

/**
 * Handle uuid column for models
 */
trait Uuid
{

    /**
     * Boot function from laravel.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            //if uuidPrefix is present use this instead 
            if(isset($model->uuidPrefix))
            {
                $short = $model->uuidPrefix;
            }else
            {
                $short = mb_substr($model->getTable(), 0, 5);
            }
            $model->uuid = $short.'-'.generate_token();
        });
    }
}