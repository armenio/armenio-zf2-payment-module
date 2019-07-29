<?php
/**
 * Rafael Armenio <rafael.armenio@gmail.com>
 *
 * @link http://github.com/armenio for the source repository
 */

namespace Armenio\Payment\CreditCard;

use Armenio\Payment\AbstractPayment;
use Cielo\API30\Ecommerce\CieloEcommerce;
use Cielo\API30\Ecommerce\Environment;
use Cielo\API30\Ecommerce\Payment;
use Cielo\API30\Ecommerce\Request\CieloRequestException;
use Cielo\API30\Ecommerce\Sale;
use Cielo\API30\Merchant;
use Zend\Json;

/**
 * Class Cielo2
 * @package Armenio\Payment\CreditCard
 */
class Cielo2 extends AbstractPayment
{
    /**
     * @var array
     */
    protected $purchase = [
        'token' => '',
        'total' => 0,
    ];

    /**
     * @var array
     */
    protected $card = [
        'number' => 0,
        'year' => 0,
        'month' => 0,
        'security' => 0,
        'name' => '',
        'flag' => '',
    ];

    /**
     * @var array
     */
    protected $options = [
        'installments' => 1,
    ];

    /**
     * @var array
     */
    protected $configs = [
        'identity' => '',
        'credential' => '',
    ];

    /**
     * @var bool
     */
    protected $devMode = true;

    /**
     * @param string|array $configs
     * @return $this
     */
    public function setConfigs($configs)
    {
        if (is_string($configs)) {
            try {
                $configs = Json\Json::decode($configs, 1);
            } catch (Json\Exception\RecursionException $e2) {

            } catch (Json\Exception\RuntimeException $e) {

            } catch (Json\Exception\InvalidArgumentException $e3) {

            } catch (Json\Exception\BadMethodCallException $e4) {

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
     * @param array $purchase
     * @return $this
     */
    public function setPurchase($purchase = [])
    {
        foreach ($purchase as $key => $value) {
            if (isset($this->purchase[$key])) {
                $this->purchase[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * @param null $key
     * @return array|mixed
     */
    public function getPurchase($key = null)
    {
        if ($key !== null) {
            return $this->purchase[$key];
        }

        return $this->purchase;
    }

    /**
     * @param array $card
     * @return $this
     */
    public function setCard($card = [])
    {
        foreach ($card as $key => $value) {
            if (isset($this->card[$key])) {
                $this->card[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * @param null $key
     * @return array|mixed
     */
    public function getCard($key = null)
    {
        if ($key !== null) {
            return $this->card[$key];
        }

        return $this->card;
    }

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
     * @param bool $devMode
     * @return $this
     */
    public function setDevMode($devMode = true)
    {
        $this->devMode = $devMode;
        return $this;
    }

    /**
     * @return bool
     */
    public function getDevMode()
    {
        return $this->devMode;
    }

    /**
     * @return array
     */
    public function callback()
    {
        $environment = $this->devMode ? Environment::sandbox() : Environment::production();

        // Merchant
        $merchant = new Merchant($this->configs['identity'], $this->configs['credential']);

        // Instância de Sale informando o ID do pedido na loja
        $sale = new Sale($this->purchase['token']);

        // Instância de Customer informando o nome do cliente
        //$customer = $sale->customer($this->card['name']);

        // Instância de Payment informando o valor do pagamento
        $payment = $sale->payment($this->purchase['total'], $this->options['installments']);
        $payment->setCapture(true);

        // Instância de Credit Card utilizando os dados de teste
        $payment->setType(Payment::PAYMENTTYPE_CREDITCARD);
        $creditCard = $payment->creditCard($this->card['security'], ucfirst($this->card['flag']));
        $creditCard->setExpirationDate($this->card['month'] . '/' . $this->card['year'])
            ->setCardNumber($this->card['number'])
            ->setHolder($this->card['name']);

        // Crie o pagamento na Cielo
        try {
            $response = (new CieloEcommerce($merchant, $environment))->createSale($sale);

            // dados retornados pela Cielo
            $status = (int)$response->getPayment()->getStatus();

            $result = [
                'ProofOfSale' => $response->getPayment()->getProofOfSale(),
                'Tid' => $response->getPayment()->getTid(),
                'AuthorizationCode' => $response->getPayment()->getAuthorizationCode(),
                'SoftDescriptor' => $response->getPayment()->getSoftDescriptor(),
                'PaymentId' => $response->getPayment()->getPaymentId(),
                //'ECI' => $response->getPayment()->getECI(),
                'Status' => $status,
                'ReturnCode' => $response->getPayment()->getReturnCode(),
                'ReturnMessage' => $response->getPayment()->getReturnMessage(),
                'cieloResponse' => $response,
            ];

            if ($status !== 2) {
                $result += [
                    'error' => 'Pagamento não autorizado pela operadora do cartão.',
                    'message' => 'Não Autorizado',
                    'status' => $status,
                ];
            }
        } catch (CieloRequestException $e) {
            $result = [
                'error' => 'Ocorreu um problema durante a requisição.',
                'message' => $e->getCieloError()->getMessage(),
            ];
        }

        return $result;
    }
}