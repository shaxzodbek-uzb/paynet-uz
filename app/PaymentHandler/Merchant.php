<?php
namespace App\PaymentHandler;

use App\PaymentHandler\Response;

class Merchant
{
    public $config;
    public $request;
    public $response;

    public function __construct($request, $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    public function Authorize()
    {
        if (
            env('PAYNET_LOGIN', '') != $this->request->params['account']['login'] ||
            env('PAYNET_PASSWORD', '') != $this->request->params['account']['password']
        ) {
            $this->response->response(
                $this->request,
                'Insufficient privilege to perform this method.',
                Response::ERROR_INSUFFICIENT_PRIVILEGE
            );
        }
        return true;
    }
}