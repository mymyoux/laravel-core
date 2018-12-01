<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 11/10/2014
 * Time: 19:14
 */

namespace Core\Exception;


class ApiException extends Exception{

    public $object;
    protected $cleanMessage;
    public $fatal = true;
    public function __construct($message = "", $readable_message = NULL, $fatal = True) {

        $this->fatal = $fatal;
    	$this->cleanMessage = $readable_message??$message;
       
        parent::__construct($message);
    }
    public function toJsonObject()
    {
        $data = 
            [
                "message" => $this->getMessage(),
                "readable_message"=>$this->getCleanErrorMessage(),
                "file" => $this->getFile(),
                "line" => $this->getLine(),
                "type" => get_class($this),
                "fatal" => $this->fatal,
                "code" => $this->getCode(),
                "api" => True,
                "trace" => $this->getTrace(),
            ];
        return $data;
    }
    public function getCleanErrorMessage()
    {
    	return $this->cleanMessage;
    }
    public function clone()
    {
        return new ApiException($this->cleanMessage, $this->code, $this->previous, $this->object, $this->fatal);
    }
     public static function unserialize($data)
    {
        $cls = $data["type"];
        $message = isset($data["message"])?$data["message"]:"";
        $code = isset($data["code"])?$data["code"]:NULL;
        $fatal = isset($data["fatal"])?$data["fatal"]:false;

        $exception = new $cls($message, $code, NULL, NULL, $fatal);
        return $exception;
    }

}
