<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\WorkbenchInterface;
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
     * @param WorkbenchInterface $workbench
     * @param string $aliasOrClassOrPath
     * @return CommunicationChannelInterface
     */
    public static function createFromString(WorkbenchInterface $workbench, string $aliasOrClassOrPath) : CommunicationChannelInterface
    {
        $selector = new CommunicationChannelSelector($workbench, $aliasOrClassOrPath);
        return parent::createFromSelector($selector);
    }

    /**
     * 
     * @param string $name
     * @param string $prototype
     * @param UxonObject $uxon
     * @param WorkbenchInterface $workbench
     */
    public static function createFromUxon(string $prototype, UxonObject $uxon, WorkbenchInterface $workbench) : CommunicationChannelInterface
    {
        $channel = self::createFromString($workbench, $prototype);
        $channel->importUxonObject($uxon);
        return $channel;
    }
}