<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Selectors\FacadeSelectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Selectors\CommunicationChannelSelectorInterface;
use exface\Core\Interfaces\Communication\CommunicationChannelInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Selectors\CommunicationChannelSelector;

/**
 * Produces facades
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class CommunicationChannelFactory extends AbstractSelectableComponentFactory
{

    /**
     *
     * @param FacadeSelectorInterface $name_resolver            
     * @return FacadeInterface
     */
    public static function create(string $name, CommunicationChannelSelectorInterface $selector) : CommunicationChannelInterface
    {
        return parent::createFromSelector($selector, [$name]);
    }

    /**
     * 
     * @param string $name
     * @param string $prototype
     * @param UxonObject $uxon
     * @param WorkbenchInterface $workbench
     */
    public static function createFromUxon(string $name, string $prototype, UxonObject $uxon, WorkbenchInterface $workbench) : CommunicationChannelInterface
    {
        $selector = new CommunicationChannelSelector($workbench, $prototype);
        $channel = self::create($name, $selector);
        $channel->importUxonObject($uxon);
    }
}