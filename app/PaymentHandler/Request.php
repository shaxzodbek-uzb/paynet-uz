<?php
namespace App\PaymentHandler;

use App\PaymentHandler\Response;

class Request
{

    public $method;

    const ARGUMENTS_PerformTransaction  = 'PerformTransactionArguments';
    const ARGUMENTS_CheckTransaction    = 'CheckTransactionArguments';
    const ARGUMENTS_GetStatement        = 'GetStatementArguments';
    const ARGUMENTS_CancelTransaction   = 'CancelTransactionArguments';
    const ARGUMENTS_GetInformation      = 'GetInformationArguments';

    const METHOD_PerformTransaction     = 'PerformTransaction';
    const METHOD_CheckTransaction       = 'CheckTransaction';
    const METHOD_GetStatement           = 'GetStatement';
    const METHOD_CancelTransaction      = 'CancelTransaction';
    const METHOD_GetInformation         = 'GetInformation';

    public $params;
    private $response;

    public function __construct($response)
    {
        $this->response = $response;
        $this->params = $this->getRequestArray();
        $this->loadAccount();

        switch ($this->params['method']) {
            case self::METHOD_PerformTransaction:
                $this->paramsPerformTransaction($this->params[self::ARGUMENTS_PerformTransaction]);
                break;
            case self::ARGUMENTS_CheckTransaction:
                $this->paramsCheckTransaction($this->params[self::ARGUMENTS_CheckTransaction]);
                break;
            case self::ARGUMENTS_GetStatement:
                $this->paramsStament($this->params[self::ARGUMENTS_GetStatement]);
                break;
            case self::ARGUMENTS_CancelTransaction:
                $this->paramsCancel($this->params[self::ARGUMENTS_CancelTransaction]);
                break;
            case self::METHOD_GetInformation:
                $this->paramsInformation($this->params);
                break;
            default:
                $this->response->response($this, 'Error in request', Response::ERROR_METHOD_NOT_FOUND);
        }
    }
    public function loadAccount()
    {
        $this->params['serviceId'] = $this->params['params']['serviceId'];
    }
    public function getRequestArray()
    {
        $request_body  = file_get_contents('php://input');
        return json_decode($request_body, true);
    }
    public function paramsPerformTransaction($par)
    {
        $key = $par['parameters'];
        if (isset($key['paramValue'])) {
            $key = $key['paramValue'];
        } else {
            $keys = $key;
            foreach ($keys as $k) {
                if ($k['paramKey'] == 'key') {
                    $key = $k['paramValue'];
                }
            }
        }
        $res = [
            'method' => self::METHOD_PerformTransaction,
            'amount' => $par['amount'],
            'transactionId' => $par['transactionId'],
            'transactionTime' => $par['transactionTime'],
            'key' => $key,
            'params' => $par['parameters']
        ];
        $this->params = array_merge($this->params, $res);
    }
    public function paramsCheckTransaction($par)
    {
        $res = [
            'method' => self::METHOD_CheckTransaction,
            'transactionId' => $par['params']['transactionId'],
            'transactionTime' => $par['params']['transactionTime'],
        ];
        $this->params = array_merge($this->params, $res);
    }

    private function paramsStament($par)
    {
        $res = [
            'method' => self::METHOD_GetStatement,
            'dateFrom' => $par['dateFrom'],
            'dateTo' => $par['dateTo']
        ];
        $this->params = array_merge($this->params, $res);
    }

    private function paramsCancel($par)
    {
        $res = [
            'method' => self::METHOD_CancelTransaction,
            'transactionId' => $par['transactionId'],
            'transactionTime' => $par['transactionTime']
        ];
        $this->params = array_merge($this->params, $res);
    }

    private function paramsInformation($par)
    {
        $res = [
            'method' => self::METHOD_GetInformation,
            'key' => $par['params']['fields']['user_id']
        ];
        $this->params = array_merge($this->params, $res);
    }
}