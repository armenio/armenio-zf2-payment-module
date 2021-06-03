<?php
/**
 * Rafael Armenio <rafael.armenio@gmail.com>
 *
 * @link http://github.com/armenio
 */

namespace Armenio\Payment\CreditCard\Cielo2;

use Cielo\API30\Ecommerce\Request\AbstractRequest;
use Cielo\API30\Environment;
use Cielo\API30\Merchant;
use Zend\Json\Json;

/**
 * Class CardBinRequest
 *
 * @package Armenio\Payment\CreditCard\Cielo2
 */
class CardBinRequest extends AbstractRequest
{

    /**
     * @var Environment
     */
    private $environment;

    /**
     * CardBinRequest constructor.
     *
     * @param Merchant $merchant
     * @param Environment $environment
     */
    public function __construct(Merchant $merchant, Environment $environment)
    {
        parent::__construct($merchant);

        $this->environment = $environment;
    }

    /**
     * @param $cardBin
     *
     * @return mixed
     * @throws \Cielo\API30\Ecommerce\Request\CieloRequestException
     */
    public function execute($cardBin)
    {
        $url = $this->environment->getApiQueryURL() . '1/cardBin/' . $cardBin;

        return $this->sendRequest('GET', $url);
    }

    /**
     * @param $json
     *
     * @return mixed
     */
    protected function unserialize($json)
    {
        return Json::decode($json, true);
    }
}
