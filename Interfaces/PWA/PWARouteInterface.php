<?php
namespace exface\Core\Interfaces\PWA;

use exface\Core\Interfaces\iCanBeConvertedToUxon;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface PWARouteInterface extends iCanBeConvertedToUxon
{
    public function getPWA() : ProgressiveWebAppInterface;
}