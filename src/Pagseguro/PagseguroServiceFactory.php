<?php
/**
 * Rafael Armenio <rafael.armenio@gmail.com>
 *
 * @link http://github.com/armenio for the source repository
 */
 
namespace Armenio\Payment\Pagseguro;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 *
 *
 * PagseguroServiceFactory
 * @author Rafael Armenio <rafael.armenio@gmail.com>
 *
 *
 */
class PagseguroServiceFactory implements FactoryInterface
{
    /**
     * zend-servicemanager v2 factory for creating Pagseguro instance.
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @returns Pagseguro
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $pagseguro = new Pagseguro();
        return $pagseguro;
    }
}
