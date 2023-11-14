<?php
namespace App\PaymentHandler;

class PaymentException extends \Exception{

    public $response;
    
    public function __construct($response)
    {
        $this->response = $response;
    }
    
    public function setReponse($reponse)
    {
        $this->response = $reponse;
    }

    public function response()
    {
        if (is_string($this->response)) {
            throw new \Exception('Response is not an object');
        }

        return $this->response->send();
    }

}
