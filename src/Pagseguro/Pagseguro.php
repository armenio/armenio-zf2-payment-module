<?php
/**
 * Rafael Armenio <rafael.armenio@gmail.com>
 *
 * @link http://github.com/armenio for the source repository
 */

namespace Armenio\Payment\Pagseguro;

use Armenio\Payment\AbstractPayment;
use Zend\Json;

/**
 * Class Pagseguro
 * @package Armenio\Payment\Pagseguro
 */
class Pagseguro extends AbstractPayment
{
    /**
     * @var array
     */
    protected $options = [
        'purchase' => [],
        'user' => [],
        'redirectUrl' => '',
        'notificationURL' => '',
    ];

    /**
     * @var array
     */
    protected $credentials = [
        'email' => '',
        'token' => '',
    ];

    /**
     * @param array $options
     * @return $this
     */
    public function setOptions($options = [])
    {
        foreach ($options as $optionKey => $optionValue) {
            if (isset($this->options[$optionKey])) {
                $this->options[$optionKey] = $optionValue;
            }
        }

        return $this;
    }

    /**
     * @param null $option
     * @return array|mixed
     */
    public function getOptions($option = null)
    {
        if ($option !== null) {
            return $this->options[$option];
        }

        return $this->options;
    }

    /**
     * @param string $jsonStringCredentials
     * @return $this
     */
    public function setCredentials($jsonStringCredentials = '')
    {
        try {
            $options = Json\Json::decode($jsonStringCredentials, 1);
            foreach ($options as $optionKey => $optionValue) {
                if (isset($this->credentials[$optionKey])) {
                    $this->credentials[$optionKey] = $optionValue;
                }
            }

            $isException = false;
        } catch (Json\Exception\RuntimeException $e) {
            $isException = true;
        } catch (Json\Exception\RecursionException $e2) {
            $isException = true;
        } catch (Json\Exception\InvalidArgumentException $e3) {
            $isException = true;
        } catch (Json\Exception\BadMethodCallException $e4) {
            $isException = true;
        }

        if ($isException === true) {
            //código em caso de problemas no decode
        }

        return $this;
    }

    /**
     * @param null $credential
     * @return array|mixed
     */
    public function getCredentials($credential = null)
    {
        if ($credential !== null) {
            return $this->credentials[$credential];
        }

        return $this->credentials;
    }

    /**
     * @return string
     *
     * $this->options['purchase']['token']
     *
     * $this->options['purchase']['items']
     * $this->options['purchase']['items'][0]['id']
     * $this->options['purchase']['items'][0]['name']
     * $this->options['purchase']['items'][0]['quantity']
     * $this->options['purchase']['items'][0]['price']
     *
     * $this->options['purchase']['discount']
     * $this->options['purchase']['shipping_price']
     * $this->options['purchase']['shipping_name']
     *
     * $this->options['user']['name']
     * $this->options['user']['email']
     * $this->options['user']['phone']
     * $this->options['user']['cpf']
     */
    public function button()
    {
        $paymentRequest = new \PagSeguroPaymentRequest();
        $paymentRequest->setCurrency('BRL');
        $paymentRequest->setReference($this->options['purchase']['token']);


        foreach ($this->options['purchase']['items'] as $item) {
            $paymentRequest->addItem($item['id'], $item['name'], $item['quantity'], $item['price']);
        }

        if ($this->options['purchase']['discount'] > 0) {
            $paymentRequest->setExtraAmount($this->options['purchase']['discount'] * -1);
        }

        if ($this->options['purchase']['shipping_price'] > 0) {
            $paymentRequest->addItem('frete', 'Frete por ' . $this->options['purchase']['shipping_name'], 1, $this->options['purchase']['shipping_price']);
        }

        // customer information.
        $paymentRequest->setSender(
            $this->options['user']['name'],
            $this->options['user']['email'],
            $this->options['user']['phone'] ? mb_substr($this->options['user']['phone'], 1, 2) : '',
            $this->options['user']['phone'] ? preg_replace('/[^\d]/', '', mb_substr($this->options['user']['phone'], 5)) : '',
            'CPF',
            $this->options['user']['cpf']
        );

        $paymentRequest->setRedirectURL($this->options['redirectUrl']);
        $paymentRequest->setNotificationURL($this->options['notificationURL']);

        try {
            // Register this payment request in PagSeguro to obtain the payment URL to redirect your customer.
            $url = $paymentRequest->register(new \PagSeguroAccountCredentials($this->credentials['email'], $this->credentials['token']));

            return '<a class="btn btn-primary animated" href="' . $url . '" target="_blank">Pagar com PagSeguro</a>';
        } catch (\PagSeguroServiceException $e) {
            return $e->getMessage();
        }
    }

    /**
     * @param $notificationCode
     * @return array|string
     */
    public function check($notificationCode)
    {
        try {
            $transaction = \PagSeguroNotificationService::checkTransaction(new \PagSeguroAccountCredentials($this->credentials['email'], $this->credentials['token']), $notificationCode);

            return [
                'code' => $transaction->getCode(),
                'reference' => $transaction->getReference()
            ];
        } catch (\PagSeguroServiceException $e) {
            return $e->getMessage();
        }
    }

    /**
     * @param $code
     * @return array|string
     */
    public function search($code)
    {
        try {
            $transaction = \PagSeguroTransactionSearchService::searchByCode(new \PagSeguroAccountCredentials($this->credentials['email'], $this->credentials['token']), $code);

            return [
                'status' => $transaction->getStatus()->getTypeFromValue()
            ];
        } catch (\PagSeguroServiceException $e) {
            return $e->getMessage();
        }
    }

    /**
     * @return bool
     */
    public function callback()
    {
        return true;
    }
}