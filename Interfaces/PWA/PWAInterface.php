<?php
namespace exface\Core\Interfaces\PWA;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface PWAInterface extends WorkbenchDependantInterface, iCanBeConvertedToUxon
{
    public function generateModel() : \Generator;
    
    public function getFacade(): FacadeInterface;
    
    public function getStartPage() : UiPageInterface;
}