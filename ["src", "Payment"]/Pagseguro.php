<?php
namespace Armenio\Payment\Payment;
use Armenio\Payment\Payment;

use Zend\Json\Json;

use Armenio\Currency as ArmenioCurrency;

/**
* Pagseguro
* 
* Processa pagamentos pelo Pagseguro
*/
class Pagseguro extends Payment
{	
	public $credentials = array(
		'email' => '',
		'token' => ''
	);

	/**
	* Constructor
	* 
	* @param array $options
	* @return __construct
	*/
	public function __construct($options = array())
	{
		$userParam = 'config';
		if( ! empty( $options[$userParam] ) ){

			try{
				$config = Json::decode($options[$userParam], 1);

				$this->credentials['email'] = $config['email'];
				$this->credentials['token'] = $config['token'];

				$isException = false;
			} catch (\Zend\Json\Exception\RuntimeException $e) {
				$isException = true;
			} catch (\Zend\Json\Exception\RecursionException $e2) {
				$isException = true;
			} catch (\Zend\Json\Exception\InvalidArgumentException $e3) {
				$isException = true;
			} catch (\Zend\Json\Exception\BadMethodCallException $e4) {
				$isException = true;
			}

			if( $isException === true ){
				//código em caso de problemas no decode
			}
		}

		$userParam = 'dataPedido';
		if( ! empty( $options[$userParam] ) ){
			$this->$userParam = $options[$userParam];
		}

		$userParam = 'dataCliente';
		if( ! empty( $options[$userParam] ) ){
			$this->$userParam = $options[$userParam];
		}

		$userParam = 'redirectUrl';
		if( ! empty( $options[$userParam] ) ){
			$this->$userParam = $options[$userParam];
		}

		$userParam = 'notificationURL';
		if( ! empty( $options[$userParam] ) ){
			$this->$userParam = $options[$userParam];
		}
	}

	public function button()
	{

		$paymentRequest = new \PagSeguroPaymentRequest();
		$paymentRequest->setCurrency('BRL');
		$paymentRequest->setReference($this->dataPedido['codigo']);

		
		foreach ($this->dataPedido['ComponentProdutosDoPedido'] as $product) {
			$paymentRequest->addItem($product['component_produtos_id'], sprintf('%s %s', $product['ComponentProdutos']['titulo'], $product['ComponentProdutos']['chapeu']), $product['quantity'], $product['preco']);
		}

		if( $this->dataPedido['desconto'] > 0 ){
			$paymentRequest->setExtraAmount($this->dataPedido['desconto']*-1);
		}

		if( $this->dataPedido['shipping_price'] > 0 ){
			$paymentRequest->addItem('frete', sprintf('Frete por %s', $this->dataPedido['ComponentFormasDeEntrega']['titulo']), 1, $this->dataPedido['shipping_price']);
		}

		// customer information.
		$paymentRequest->setSender(
			$this->dataCliente['titulo'],
			$this->dataCliente['email'],
			mb_substr($this->dataCliente['telefone'], 1, 2),
			preg_replace('/[^\d]/', '', mb_substr($this->dataCliente['telefone'], 5)),
			$this->dataCliente['tipo'] == 'pf' ? 'CPF' : 'CNPJ',
			$this->dataCliente['cpf_cnpj']
		);

		$paymentRequest->setRedirectUrl($this->redirectUrl);
		$paymentRequest->addParameter('notificationURL', $this->notificationURL);

		try{
			// Register this payment request in PagSeguro to obtain the payment URL to redirect your customer.
			$url = $paymentRequest->register(new \PagSeguroAccountCredentials($this->credentials['email'], $this->credentials['token']));

			return sprintf('<a class="btn btn-info btn-fill btn-lg" href="%s" target="_blank">Pagar com PagSeguro</a>', $url);
		} catch (\PagSeguroServiceException $e) {
			return $e->getMessage();
		} 
	}

	public function check($notificationCode)
	{
		try{
			$transaction = \PagSeguroNotificationService::checkTransaction(new \PagSeguroAccountCredentials($this->credentials['email'], $this->credentials['token']), $notificationCode);

			return array(
				'code' => $transaction->getCode(),
				'reference' => $transaction->getReference()
			);
		} catch (\PagSeguroServiceException $e) {
			return $e->getMessage();
		} 
	}

	public function search($code)
	{
		try{
			$transaction = \PagSeguroTransactionSearchService::searchByCode(new \PagSeguroAccountCredentials($this->credentials['email'], $this->credentials['token']), $code);

			return array(
				'status' => $transaction->getStatus()->getTypeFromValue()
			);
		} catch (\PagSeguroServiceException $e) {
			return $e->getMessage();
		} 
	}

	public function callback()
	{
		return true;
	}
}