<?php
namespace Core\Api\Annotations;
use Core\Exception\Exception;
use Core\Exception\ApiException;


/**
 *
 * @Annotation
 * @Target({"METHOD","CLASS"})
 */
class Gate extends CoreAnnotation
{
    public $allows;
    public function handle($config)
    {
        if(isset($this->allows))
        {
            $this->allows = array_map('trim', explode(',', $this->allows));
            parent::handle($config);
        }
    }
}
