<?php
/**
 * Rafael Armenio <rafael.armenio@gmail.com>
 *
 * @link http://github.com/armenio
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
        'address' => [
            'zip_code' => '',
            'uf' => '',
            'city' => '',
            'neighborhood' => '',
            'street' => '',
            'number_complement' => '',
        ],
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
    protected $configs = [
        'identity' => '',
        'credential' => '',
        'provider' => '',
        'nosso_numero' => '',
        'demonstrative' => '',
        'instructions' => '',
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
        $merchant = new Merchant($this->configs['identity'], $this->configs['credential']);

        // Instância de Sale informando o ID do pedido na loja
        $sale = new Sale($this->purchase['token']);

        // Instância de Customer informando o nome do cliente
        $sale->customer($this->customer['name'])
            ->setIdentity($this->customer['cpf'])
            ->setIdentityType('CPF')
            ->address()->setZipCode($this->customer['address']['zip_code'])
            ->setCountry('BRA')
            ->setState($this->customer['address']['uf'])
            ->setCity($this->customer['address']['city'])
            ->setDistrict($this->customer['address']['neighborhood'])
            ->setStreet($this->customer['address']['street'])
            ->setNumber($this->customer['address']['number_complement']);

        // Instância de Payment informando o valor do pagamento
        $payment = $sale->payment($this->purchase['total']);

        // Instância do boleto
        $payment->setType(Payment::PAYMENTTYPE_BOLETO)
            ->setProvider($this->configs['provider'])
            ->setAddress($this->assignor['address'])
            //->setBoletoNumber($this->configs['numer'])
            ->setAssignor($this->assignor['name'])
            ->setDemonstrative($this->configs['demonstrative'])
            ->setExpirationDate(date('d/m/Y', strtotime('+1 day')))
            ->setIdentification($this->purchase['token'])
            ->setInstructions($this->configs['instructions']);

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