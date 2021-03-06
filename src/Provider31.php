<?php

/**
 *      Main class for EasyPay-Provider 3.1
 *
 *      @package php_EasyPay
 *      @version 1.1
 *      @author Dmitry Shovchko <d.shovchko@gmail.com>
 *
 */

namespace EasyPay;

class Provider31
{
    /**
     *      @var array
     */
    protected static $options = array(
        'ServiceId' => 0,
        'UseSign' => false,
        'EasySoftPKey' => '',
        'ProviderPKey' => '',
    );

    /**
     *      @var \EasyPay\Callback
     */
    protected static $cb;

    /**
     *      @var mixed
     */
    protected $request;

    /**
     *      @var Provider31\Response
     */
    protected $response;

    /**
     *      Provider31 constructor
     *
     *      @param array $options
     *      @param Callback $cb
     *      @param \Debulog\LoggerInterface $log
     */
    public function __construct(array $options, \EasyPay\Callback $cb, \Debulog\LoggerInterface $log)
    {
        self::$options = array_merge(self::$options, $options);
        self::$cb = $cb;

        Log::set($log);
    }

    /**
     *      Get and process request, echo response
     *
     */
    public function process()
    {
        try
        {
            //      get request
            $this->get_request();

            //      get response
            $this->response = $this->get_response();

            Log::instance()->add('the request was processed successfully');
        }
        catch (\Exception $e)
        {
            $this->response = $this->get_error_response($e);

            Log::instance()->add('the request was processed with an error');
        }

        //      output response
        $this->response->sign_and_out(self::$options);
    }

    /**
     *      method to create a specific class of request
     *
     */
    protected function get_request()
    {
        $raw = new Provider31\Request\RAW();

        $r = new Provider31\Request\General($raw);
        $c = '\\EasyPay\\Provider31\\Request\\'.$r->Operation();

        $this->request = new $c($raw);

        //      validate request
        $this->request->validate_request(self::$options);
        Log::instance()->debug('request is valid');

        //      verify sign
        $this->request->verify_sign(self::$options);
        Log::instance()->debug('signature of request is correct');
    }

    /**
     *      generate response
     *
     *      @return mixed
     */
    protected function get_response()
    {
        $m = 'response_'.$this->request->Operation();
        return $this->$m();
    }

    /**
     *      run check callback and generate a response
     *
     *      @return Provider31\Response\Check
     */
    private function response_Check()
    {
        Log::instance()->add(sprintf('Check("%s")', $this->request->Account()));

        $accountinfo = self::$cb->check(
            $this->request->Account()
        );

        // Sending a response
        return new Provider31\Response\Check($accountinfo);
    }

    /**
     *      run payment callback and generate a response
     *
     *      @return Provider31\Response\Payment
     */
    private function response_Payment()
    {
        Log::instance()->add(sprintf('Payment("%s", "%s", "%s")', $this->request->Account(), $this->request->OrderId(), $this->request->Amount()));

        $paymentid = self::$cb->payment(
            $this->request->Account(),
            $this->request->OrderId(),
            $this->request->Amount()
        );

        // Sending a response
        return new Provider31\Response\Payment($paymentid);
    }

    /**
     *      run confirm callback and generate a response
     *
     *      @return Provider31\Response\Confirm
     */
    private function response_Confirm()
    {
        Log::instance()->add(sprintf('Confirm("%s")', $this->request->PaymentId()));

        $orderdate = self::$cb->confirm(
            $this->request->PaymentId()
        );

        // Sending a response
        return new Provider31\Response\Confirm($orderdate);
    }

    /**
     *      run cancel callback and generate a response
     *
     *      @return Provider31\Response\Cancel
     */
    private function response_Cancel()
    {
        Log::instance()->add(sprintf('Cancel("%s")', $this->request->PaymentId()));

        $canceldate = self::$cb->cancel(
            $this->request->PaymentId()
        );

        // Sending a response
        return new Provider31\Response\Cancel($canceldate);
    }

    /**
     *      Generates an xml with an error message
     *
     *      @param mixed $e
     *
     *      @return Provider31\Response\ErrorInfo
     */
    private function get_error_response($e)
    {
        $message = $e->getMessage();

        if ($e instanceof Exception\Structure)
        {
            $message = 'Error in request';
        }
        elseif ($e instanceof Exception\Sign)
        {
            $message = 'Signature error!';
        }
        elseif ($e instanceof Exception\Runtime)
        {
            $message = 'Error while processing request';
        }

        // Sending a response
        return new Provider31\Response\ErrorInfo($e->getCode(), $message);
    }
}
