<?php

namespace App\PaymentHandler;

use App\PaymentHandler\PaymentException;

class Response
{
    const ERROR_INTERNAL_SYSTEM         = -32400;
    const ERROR_INSUFFICIENT_PRIVILEGE  = -32504;
    const ERROR_INVALID_JSON_RPC_OBJECT = -32600;
    const ERROR_METHOD_NOT_FOUND        = -32601;
    const ERROR_INVALID_AMOUNT          = -31001;
    const ERROR_TRANSACTION_NOT_FOUND   = -31003;
    const ERROR_INVALID_ACCOUNT         = -31050;
    const ERROR_COULD_NOT_CANCEL        = -31007;
    const ERROR_COULD_NOT_PERFORM       = -31008;
    const SUCCESS                       = 0;

    public $request;
    public $body;
    public $code;

    public function response($request, $body, $code){
        $this->request = $request;
        $this->body  = $body;
        $this->code = $code;
        throw new PaymentException($this);
    }
    public static function makeResponse($body){
        return $body;
    }
    public function send(){
        // response json
        header('content-type: application/json');
        if ($this->request == null)
            echo 'error';
        else 
            echo $this->body;
        
        exit();
    }

    public function setRequest($request){
        return $this->request = $request;
    }
}
