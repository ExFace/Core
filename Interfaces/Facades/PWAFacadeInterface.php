<?php
namespace exface\Core\Interfaces\Facades;

use exface\Core\Interfaces\Selectors\PWASelectorInterface;
use exface\Core\Interfaces\PWA\PWAInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface PWAFacadeInterface extends HttpFacadeInterface
{    
    /**
     * 
     * @param PWASelectorInterface|string $selectorOrString
     * @return PWAInterface
     */
    public function getPWA($selectorOrString) : PWAInterface;
}
