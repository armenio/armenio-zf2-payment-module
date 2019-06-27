<?php
/**
 * Rafael Armenio <rafael.armenio@gmail.com>
 *
 * @link http://github.com/armenio for the source repository
 */

namespace Armenio\Payment\Boleto;

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
 * @package Armenio\Payment\Boleto
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
    protected $customer = [
        'name' => '',
        'cpf' => '',
        'addressZipCode' => '',
        'addressUf' => '',
        'addressCity' => '',
        'addressNeighborhood' => '',
        'addressStreet' => '',
        'addressNumberComplement' => '',
    ];

    /**
     * @var array
     */
    protected $assignor = [
        'address' => '',
        'name' => '',
    ];

    /**
     * @var array
     */
    protected $credentials = [
        'identity' => '',
        'credential' => '',
        'provider' => '',
        'nossoNumero' => '',
        'demonstrative' => '',
        'instructions' => '',
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
     * @param array $customer
     * @return $this
     */
    public function setCustomer($customer = [])
    {
        foreach ($customer as $key => $value) {
            if (isset($this->customer[$key])) {
                $this->customer[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * @param null $key
     * @return array|mixed
     */
    public function getCustomer($key = null)
    {
        if ($key !== null) {
            return $this->customer[$key];
        }

        return $this->customer;
    }

    /**
     * @param array $assignor
     * @return $this
     */
    public function setAssignor($assignor = [])
    {
        foreach ($assignor as $key => $value) {
            if (isset($this->assignor[$key])) {
                $this->assignor[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * @param null $key
     * @return array|mixed
     */
    public function getAssignor($key = null)
    {
        if ($key !== null) {
            return $this->assignor[$key];
        }

        return $this->assignor;
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
        $sale->customer($this->customer['name'])
            ->setIdentity($this->customer['cpf'])
            ->setIdentityType('CPF')
            ->address()->setZipCode($this->customer['addressZipCode'])
            ->setCountry('BRA')
            ->setState($this->customer['addressUf'])
            ->setCity($this->customer['addressCity'])
            ->setDistrict($this->customer['addressNeighborhood'])
            ->setStreet($this->customer['addressStreet'])
            ->setNumber($this->customer['addressNumberComplement']);

        // Instância de Payment informando o valor do pagamento
        $payment = $sale->payment($this->purchase['total']);

        // Instância do boleto
        $payment->setType(Payment::PAYMENTTYPE_BOLETO)
            ->setProvider($this->credentials['provider'])
            ->setAddress($this->assignor['address'])
            //->setBoletoNumber($this->credentials['numer'])
            ->setAssignor($this->assignor['name'])
            ->setDemonstrative($this->credentials['demonstrative'])
            ->setExpirationDate(date('d/m/Y', strtotime('+1 day')))
            ->setIdentification($this->purchase['token'])
            ->setInstructions($this->credentials['instructions']);

        // Crie o pagamento na Cielo
        try {
            $response = (new CieloEcommerce($merchant, $environment))->createSale($sale);

            // dados retornados pela Cielo
            $status = (int)$response->getPayment()->getStatus();

            $result = [
                'PaymentId' => $response->getPayment()->getPaymentId(),
                'url' => $response->getPayment()->getUrl(),
                'cieloResponse' => $response,
            ];

            if ($status !== 1) {
                $result += [
                    'error' => 'Boleto não gerado pelo banco.',
                    'message' => 'Não Autorizado',
                    'status' => $status,
                ];
            } elseif ($this->devMode) {
                $result += [
                    'error' => 'devMode: Ok.',
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