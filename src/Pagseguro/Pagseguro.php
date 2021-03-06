<?php
/**
 * Rafael Armenio <rafael.armenio@gmail.com>
 *
 * @link http://github.com/armenio
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
    protected $configs = [
        'identity' => '',
        'credential' => '',
    ];

    /**
     * @param array $options
     * @return $this
     */
    public function setOptions($options = [])
    {
        if (is_array($options) && !empty($options)) {
            foreach ($options as $optionKey => $optionValue) {
                if (isset($this->options[$optionKey])) {
                    $this->options[$optionKey] = $optionValue;
                }
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
     * @param string|array $configs
     * @return $this
     */
    public function setConfigs($configs)
    {
        if (is_string($configs)) {
            try {
                $configs = Json\Json::decode($configs, 1);
            } catch (Json\Exception\RecursionException $e) {

            } catch (Json\Exception\RuntimeException $e) {

            } catch (Json\Exception\InvalidArgumentException $e) {

            } catch (Json\Exception\BadMethodCallException $e) {

            }
        }

        if (is_array($configs) && !empty($configs)) {
            foreach ($configs as $key => $value) {
                if (isset($this->configs[$key])) {
                    $this->configs[$key] = $value;
                }
            }
        }

        return $this;
    }

    /**
     * @param null $config
     * @return array|mixed
     */
    public function getConfigs($config = null)
    {
        if ($config !== null) {
            return $this->configs[$config];
        }

        return $this->configs;
    }

    /**
     * @param string $label
     * @return array
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
    public function callback()
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
            $url = $paymentRequest->register(new \PagSeguroAccountCredentials($this->configs['identity'], $this->configs['credential']));

            $result = [
                'url' => $url,
            ];
        } catch (\PagSeguroServiceException $e) {
            $result = [
                'error' => 'Ocorreu um problema durante a requisição.',
                'message' => $e->getMessage(),
            ];
        }

        return $result;
    }

    /**
     * @param $notificationCode
     * @return array|string
     * @throws \Exception
     */
    public function check($notificationCode)
    {
        try {
            $transaction = \PagSeguroNotificationService::checkTransaction(new \PagSeguroAccountCredentials($this->configs['identity'], $this->configs['credential']), $notificationCode);

            return [
                'code' => $transaction->getCode(),
                'reference' => $transaction->getReference(),
            ];
        } catch (\PagSeguroServiceException $e) {
            return $e->getMessage();
        }
    }

    /**
     * @param $code
     * @return array|string
     * @throws \Exception
     */
    public function search($code)
    {
        try {
            $transaction = \PagSeguroTransactionSearchService::searchByCode(new \PagSeguroAccountCredentials($this->configs['identity'], $this->configs['credential']), $code);

            return [
                'status' => $transaction->getStatus()->getTypeFromValue()
            ];
        } catch (\PagSeguroServiceException $e) {
            return $e->getMessage();
        }
    }
}
