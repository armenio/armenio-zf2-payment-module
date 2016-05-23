<?php
/**
 * Rafael Armenio <rafael.armenio@gmail.com>
 *
 * @link http://github.com/armenio for the source repository
 */
 
namespace Armenio\Payment\Payment;
use Armenio\Payment\AbstractPayment;

use Zend\Json\Json;

/**
* Pagseguro
* 
* Processa pagamentos pelo Pagseguro
*/
class Pagseguro extends AbstractPayment
{	
	protected $options = array(
		'dataPedido' => array(),
		'dataUsuario' => array(),
		'redirectUrl' => '',
		'notificationURL' => '',
	);

	protected $credentials = array(
		'email' => '',
		'token' => '',
	);

	public function setOptions($options = array())
	{
		foreach ( $options as $optionKey => $optionValue ) {
			if( isset( $this->options[$optionKey] ) ){
				$this->options[$optionKey] = $optionValue;
			}
		}

		return $this;
	}

	public function getOptions($option = null)
	{
		if( $option !== null ){
			return $this->options[$option];
		}

		return $this->options;
	}

	public function setCredentials($jsonStringCredentials = '')
	{
		try{
			$options = Json::decode($jsonStringCredentials, 1);
			foreach ( $options as $optionKey => $optionValue ) {
				if( isset( $this->credentials[$optionKey] ) ){
					$this->credentials[$optionKey] = $optionValue;
				}
			}

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

		return $this;
	}

	public function getCredentials($credential = null)
	{
		if( $credential !== null ){
			return $this->credentials[$credential];
		}

		return $this->credentials;
	}

	public function button()
	{
		$paymentRequest = new \PagSeguroPaymentRequest();
		$paymentRequest->setCurrency('BRL');
		$paymentRequest->setReference($this->options['dataPedido']['codigo']);

		
		foreach ($this->options['dataPedido']['ComponentProdutosDoPedido'] as $product) {
			$paymentRequest->addItem($product['component_produtos_id'], sprintf('%s %s', $product['ComponentProdutos']['titulo'], $product['ComponentProdutos']['chapeu']), $product['quantity'], $product['preco']);
		}

		if( $this->options['dataPedido']['desconto'] > 0 ){
			$paymentRequest->setExtraAmount($this->options['dataPedido']['desconto']*-1);
		}

		if( $this->options['dataPedido']['shipping_price'] > 0 ){
			$paymentRequest->addItem('frete', sprintf('Frete por %s', $this->options['dataPedido']['ComponentFormasDeEntrega']['titulo']), 1, $this->options['dataPedido']['shipping_price']);
		}

		// customer information.
		$paymentRequest->setSender(
			$this->options['dataUsuario']['titulo'],
			$this->options['dataUsuario']['email'],
			mb_substr($this->options['dataUsuario']['telefone'], 1, 2),
			preg_replace('/[^\d]/', '', mb_substr($this->options['dataUsuario']['telefone'], 5)),
			'CPF',
			$this->options['dataUsuario']['cpf']
		);

		$paymentRequest->setRedirectUrl($this->options['redirectUrl']);
		$paymentRequest->addParameter('notificationURL', $this->options['notificationURL']);

		try{
			// Register this payment request in PagSeguro to obtain the payment URL to redirect your customer.
			$url = $paymentRequest->register(new \PagSeguroAccountCredentials($this->credentials['email'], $this->credentials['token']));

			return sprintf('<a class="btn btn-fill btn-lg btn-aircode btn-aircode-primary" href="%s" target="_blank">Pagar com PagSeguro</a>', $url);
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