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
    protected $credentials = [
        'identity' => '',
        'credential' => '',
    ];

    /**
     * @var bool
     */
    protected $devMode = true;

    /**
     * @param null $credentials
     * @return $this
     */
    public function setCredentials($credentials = null)
    {
        if (is_string($credentials)) {
            try {
                $credentials = Json\Json::decode($credentials, 1);
            } catch (Json\Exception\RuntimeException $e) {
                $credentials = [];
            } catch (Json\Exception\InvalidArgumentException $e2) {
                $credentials = [];
            } catch (Json\Exception\BadMethodCallException $e3) {
                $credentials = [];
            }
        }

        foreach ($credentials as $key => $value) {
            if (isset($this->credentials[$key])) {
                $this->credentials[$key] = $value;
            }
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
        $merchant = new Merchant($this->credentials['identity'], $this->credentials['credential']);

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
                    'Status' => $status,
                ];
            } elseif ($this->devMode) {
                $result += [
                    'error' => $response->getPayment()->getReturnMessage(),
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