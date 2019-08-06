<?php
/**
 * Rafael Armenio <rafael.armenio@gmail.com>
 *
 * @link http://github.com/armenio for the source repository
 */

namespace Armenio\Payment\CreditCard;

use Armenio\Payment\AbstractPayment;
use DateTime;
use SimpleXMLElement;
use Zend\Http\Client;
use Zend\Http\Client\Adapter\Curl;
use Zend\Json;

/**
 * Class Cielo
 * @package Armenio\Payment\CreditCard
 */
class Cielo extends AbstractPayment
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
     * @return mixed
     */
    function getXml()
    {
        $requisicao = new SimpleXMLElement('<requisicao-transacao />');
        $requisicao->addAttribute('id', md5($this->purchase['token']));
        $requisicao->addAttribute('versao', '1.2.1');

        $dadosEc = $requisicao->addChild('dados-ec');
        $dadosEc->addChild('numero', $this->configs['identity']); //Número de afiliação da loja com a Cielo.
        $dadosEc->addChild('chave', $this->configs['credential']); //Chave de acesso da loja atribuída pela Cielo.

        $dadosPortador = $requisicao->addChild('dados-portador');
        $dadosPortador->addChild('numero', $this->card['number']); //Número do cartão.
        $dadosPortador->addChild('validade', $this->card['year'] . $this->card['month']); //Validade do cartão no formato aaaamm. Exemplo: 201212 (dez/2012).
        $dadosPortador->addChild('indicador', 1); //Indicador sobre o envio do Código de segurança: 0 – não informado, 1 – informado, 2 – ilegível, 9 – inexistente
        $dadosPortador->addChild('codigo-seguranca', $this->card['security']); //Obrigatório se o indicador for 1
        $dadosPortador->addChild('nome-portador', $this->card['name']); //Nome como impresso no cartão
        //$dadosPortador->addChild('token', null); //Token que deve ser utilizado em substituição aos dados do cartão para uma autorização direta ou uma transação recorrente. Não é permitido o envio do token junto com os dados do cartão na mesma transação.

        $dadosPedido = $requisicao->addChild('dados-pedido');
        $dadosPedido->addChild('numero', $this->purchase['token']); //Número do pedido da loja. Recomenda-se que seja um valor único por pedido.
        $dadosPedido->addChild('valor', $this->purchase['total']); //Valor a ser cobrado pelo pedido (já deve incluir valoresde frete, embrulho, custos extras, taxa de embarque, etc). Esse valor é o que será debitado do consumidor.
        $dadosPedido->addChild('moeda', 986); //Código numérico da moeda na norma ISO 4217. Para o Real, o código é 986.
        $dadosPedido->addChild('data-hora', (new DateTime())->format('Y-m-d\TH:i:s')); //aaaa-MM-ddTHH24:mm:ss
        //$dadosPedido->addChild('descricao', null); //Descrição do pedido
        $dadosPedido->addChild('idioma', 'PT'); //Idioma do pedido: PT (português), EN (inglês) ou ES (espanhol). Com base nessa informação é definida a língua a ser utilizada nas telas da Cielo. Caso não seja enviado, o sistema assumirá “PT”.
        //$dadosPedido->addChild('taxa-embarque', null); //Montante do valor da autorização que deve ser destinado à taxa de embarque.
        //$dadosPedido->addChild('soft-descriptor', null); //Texto de até 13 caracteres que será exibido na fatura do portador, após o nome do Estabelecimento Comercial.

        $formaPagamento = $requisicao->addChild('forma-pagamento');
        $formaPagamento->addChild('bandeira', $this->card['flag']); //Nome da bandeira (minúsculo): “visa”, “mastercard”, “diners”, “discover”, “elo”, “amex”, “jcb”, “aura”
        $formaPagamento->addChild('produto', $this->options['installments'] > 1 ? 2 : 1); //Código do produto: 1 – Crédito à Vista, 2 – Parcelado loja, A – Débito.
        $formaPagamento->addChild('parcelas', $this->options['installments']); //Número de parcelas. Para crédito à vista ou débito, utilizar 1.

        //$formaPagamento->addChild('url-retorno', null);

        $requisicao->addChild('autorizar', 3); //autorização direta
        $requisicao->addChild('capturar', true); //true ou false. Define se a transação será automaticamente capturada caso seja autorizada.

        //$requisicao->addChild('campo-livre', null); //Campo livre disponível para o Estabelecimento.
        //$requisicao->addChild('bin', null); //Seis primeiros números do cartão.
        //$requisicao->addChild('gerar-token', null); //Define se a transação atual deve gerar um token associado ao cartão.
        //$requisicao->addChild('avs#avs', null); //String contendo um bloco XML, encapsulado pelo CDATA, contendo as informações necessárias para realizar a consulta ao serviço.

        return $requisicao->asXML();
    }

    /**
     * @return array
     */
    public function callback()
    {
        $xml = $this->getXml();
        $params = [
            'mensagem' => $xml,
        ];

        $url = $this->devMode ? 'https://qasecommerce.cielo.com.br/servicos/ecommwsec.do' : 'https://ecommerce.cbmp.com.br/servicos/ecommwsec.do';
        $client = new Client($url);
        $client->setAdapter(new Curl());
        $client->setMethod('POST');
        $client->setOptions([
            'curloptions' => [
                CURLOPT_HEADER => false,
                CURLOPT_CONNECTTIMEOUT => 0,
                CURLOPT_TIMEOUT => 60,
            ]
        ]);

        $client->setParameterPost($params);

        try {
            $response = $client->send();

            $body = $response->getBody();

            $objSimpleXMLElement = new SimpleXMLElement($body);

            if (isset($objSimpleXMLElement->status) && (int)$objSimpleXMLElement->status === 6) {
                $result = [
                    'tid' => (string)$objSimpleXMLElement->tid,
                    'pan' => (string)$objSimpleXMLElement->pan,
                    'status' => (int)$objSimpleXMLElement->status,
                    'request' => $xml,
                    'response' => utf8_encode($body),
                ];
            } else {
                $error = 'Ocorreu um problema durante a requisição.';

                if (isset($objSimpleXMLElement->status) && (int)$objSimpleXMLElement->status === 5) {
                    $error = 'Pagamento não autorizado pela operadora do cartão.';
                };

                $result = [
                    'error' => $error,
                    'message' => (string)$objSimpleXMLElement->mensagem,
                    'request' => $xml,
                    'response' => utf8_encode($body),
                ];
            }
        } catch (Client\Adapter\Exception\TimeoutException $e) {
            $result = [
                'error' => $e->getMessage(),
            ];
        } catch (Client\Adapter\Exception\RuntimeException $e) {
            $result = [
                'error' => $e->getMessage(),
            ];
        }

        return $result;
    }
}