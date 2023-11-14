<?php

namespace App\PaymentHandler;

use App\Models\PaynetTransaction;
use App\Models\User;
use App\PaymentHandler\DataFormat;
use App\PaymentHandler\Response;

class Paynet
{
    public $request;
    public $response;
    public $merchant;

    public function __construct()
    {
        $this->response  = new Response();
        $this->request  = new Request($this->response);
        $this->response->setRequest($this->request);
        $this->merchant = new Merchant($this->request, $this->response);
    }
    public function run()
    {
        $this->merchant->Authorize();
        switch ($this->request->params['method']) {
            case Request::METHOD_CheckTransaction:
                $body = Response::makeResponse($this->CheckTransaction());
                break;
            case Request::METHOD_PerformTransaction:
                $body = Response::makeResponse($this->PerformTransaction());
                break;
            case Request::METHOD_CancelTransaction:
                $body = Response::makeResponse($this->CancelTransaction());
                break;
            case Request::METHOD_GetStatement:
                $body = $this->GetStatement();
                break;
            case Request::METHOD_GetInformation:
                $body = Response::makeResponse($this->GetInformation());
                break;
            default:
                $this->response->response($this->request, 'Method not found.', Response::ERROR_METHOD_NOT_FOUND);
        }
        $this->response->response($this->request, $body, Response::SUCCESS);
    }


    private function CheckTransaction()
    {
        $transaction = $this->getTransactionBySystemTransactionId();
        $transactionState = ($transaction->state == PaynetTransaction::STATE_CANCELLED) ? 2 : 1;

        return "<ns2:CheckTransactionResult xmlns:ns2=\"http://uws.provider.com/\">" .
            "<errorMsg>Success</errorMsg>" .
            "<status>0</status>" .
            "<timeStamp>" . DataFormat::toDateTimeWithTimeZone(now()) . "</timeStamp>" .
            "<providerTrnId>" . $this->request->params['transactionId'] . "</providerTrnId>" .
            "<transactionState>" . $transactionState . "</transactionState>" .
            "<transactionStateErrorStatus>0</transactionStateErrorStatus>" .
            "<transactionStateErrorMsg>Success</transactionStateErrorMsg>" .
            "</ns2:CheckTransactionResult>";
    }


    private function PerformTransaction()
    {
        if ($this->getTransactionBySystemTransactionId())
            return "<ns2:PerformTransactionResult xmlns:ns2=\"http://uws.provider.com/\">" .
                "<errorMsg>transaction found</errorMsg>" .
                "<status>201</status>" .
                "<timeStamp>" . DataFormat::toDateTimeWithTimeZone(now()) . "</timeStamp>" .
                "<providerTrnId>" . $this->request->params['transactionId'] . "</providerTrnId>" .
                "</ns2:PerformTransactionResult>";

        $model = $this->convertKeyToModel($this->request->params['key']);

        // TODO: check if user not found return status 302;

        if (is_null($model)) {
            return  "<ns2:PerformTransactionResult xmlns:ns2=\"http://uws.provider.com/\">" .
                "<errorMsg>Model not found</errorMsg>" .
                "<status>302</status>" .
                "<timeStamp>" . DataFormat::toDateTimeWithTimeZone(now()) . "</timeStamp>" .
                "<providerTrnId>0</providerTrnId>" .
                "</ns2:PerformTransactionResult>";
        }
        // TODO: check if user be yuridik litso can not return status 501;

        $create_time = DataFormat::timestamp(true);

        $detail = array(
            'create_time'           => $create_time,
            'perform_time'          => null,
            'cancel_time'           => null,
            'system_time_datetime'  => DataFormat::timestamp2datetime($this->request->params['transactionTime']),
            'params' => $this->request->params['params'],
            'serviceId' => $this->request->params['serviceId']
        );
        $transaction = PaynetTransaction::create([
            'system_transaction_id' => $this->request->params['transactionId'],
            'amount'                => 1 * $this->request->params['amount'],
            'currency_code'         => PaynetTransaction::CURRENCY_CODE_UZS,
            'state'                 => PaynetTransaction::STATE_CREATED,
            'updated_time'          => 1 * $create_time,
            'comment'               => (isset($this->request->params['error_note']) ? $this->request->params['error_note'] : ''),
            'detail'                => $detail,
            'transactionable_type'  => get_class($model),
            'transactionable_id'    => $model->id
        ]);

        $this->payListener(null, $transaction, 'after-pay');

        return  "<ns2:PerformTransactionResult xmlns:ns2=\"http://uws.provider.com/\">" .
            "<errorMsg>Success</errorMsg>" .
            "<status>0</status>" .
            "<timeStamp>" . DataFormat::toDateTimeWithTimeZone(now()) . "</timeStamp>" .
            "<providerTrnId>" . $this->request->params['transactionId'] . "</providerTrnId>" .
            "</ns2:PerformTransactionResult>";
    }

    private function CancelTransaction()
    {

        $transaction = $this->getTransactionBySystemTransactionId();

        if ($transaction == null || $transaction->state == PaynetTransaction::STATE_CANCELLED) {
            return  "<ns2:CancelTransactionResult xmlns:ns2=\"http://uws.provider.com/\">" .
                "<errorMsg>bekor qilingan</errorMsg>" .
                "<status>202</status>" .
                "<timeStamp>" . DataFormat::toDateTimeWithTimeZone(now()) . "</timeStamp>" .
                "<transactionState>2</transactionState>" .
                "</ns2:CancelTransactionResult>";
        }

        $transaction->state = PaynetTransaction::STATE_CANCELLED;
        $transaction->update();
        $this->payListener(null, $transaction, 'cancel-pay');

        return  "<ns2:CancelTransactionResult xmlns:ns2=\"http://uws.provider.com/\">" .
            "<errorMsg>Success</errorMsg>" .
            "<status>0</status>" .
            "<timeStamp>" . DataFormat::toDateTimeWithTimeZone(now()) . "</timeStamp>" .
            "<transactionState>2</transactionState>" .
            "</ns2:CancelTransactionResult>";
    }


    private function GetStatement()
    {

        $transactions = PaynetTransaction::where('state', '<>', PaynetTransaction::STATE_CANCELLED)
            ->where('created_at', '<=', DataFormat::toDateTime($this->request->params['dateTo']))
            ->where('created_at', '>=', DataFormat::toDateTime($this->request->params['dateFrom']))
            ->get();
        $statements = '';

        foreach ($transactions as $transaction) {
            $statements = $statements .
                "<statements>" .
                "<amount>" . $transaction->amount . "</amount>" .
                "<providerTrnId>" . $transaction->id . "</providerTrnId>" .
                "<transactionId>" . $transaction->system_transaction_id . "</transactionId>" .
                "<transactionTime>" . DataFormat::toDateTimeWithTimeZone($transaction->created_at) . "</transactionTime>" .
                "</statements>";
        }

        return  "<SOAP-ENV:Envelope xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:ns1=\"http://uws.provider.com/\">" .
            "<SOAP-ENV:Body>" .
            "<ns1:GetStatementResult>" .
            "<errorMsg>Success</errorMsg>" .
            "<status>0</status>" .
            "<timeStamp>" . DataFormat::toDateTimeWithTimeZone(now()) . "</timeStamp>" .
            $statements .
            "</ns1:GetStatementResult>" .
            "</SOAP-ENV:Body>" .
            "</SOAP-ENV:Envelope>";
    }

    private function GetInformation()
    {
        $model = $this->convertKeyToModel($this->request->params['key']);
        if ($model) {
            return json_encode([
                "jsonrpc" => "2.0", 
                "id" => $this->request->params['id'],
                "result" => [ 
                    "status" => "0", 
                    "timestamp" => "2021-04-30 08:00:00", 
                    "fields" => [ 
                        "name" => $model->name,
                    ]
                ]
            ]);
        } else {
            return  "<ns2:GetInformationResult xmlns:ns2=\"http://uws.provider.com/\">" .
                "<errorMsg>Not Found</errorMsg>" .
                "<status>302</status>" .
                "<timeStamp>" . DataFormat::toDateTimeWithTimeZone(now()) . "</timeStamp>" .
                "</ns2:GetInformationResult>";
        }
    }


    private function getTransactionBySystemTransactionId()
    {
        return PaynetTransaction::where('system_transaction_id', $this->request->params['transactionId'])->first();
    }
    public function convertKeyToModel($key)
    {
        $model = User::find($key);
        return $model;
    }

    public function payListener($model, $transaction, $hook)
    {
        info([
            'model' => $model,
            'transaction' => $transaction,
            'hook' => $hook
        ]);
    }

}