<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Communication\CommunicationChannelInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Selectors\CommunicationChannelSelector;
use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\CommonLogic\Selectors\CommunicationMessageSelector;
use exface\Core\Communication\Messages\GenericMessage;
use exface\Core\Communication\Messages\TextMessage;
use exface\Core\Interfaces\Selectors\CommunicationChannelSelectorInterface;
use exface\Core\CommonLogic\Communication\CommunicationChannel;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Interfaces\Selectors\CommunicationMessageSelectorInterface;

/**
 * Produces components related to the communication framework
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class CommunicationFactory extends AbstractSelectableComponentFactory
{

    public static function createFromSelector(SelectorInterface $selector, array $constructorArguments = null)
    {
        if ($selector instanceof CommunicationChannelSelectorInterface) {
            return $selector->getWorkbench()->model()->getModelLoader()->loadCommunicationChannel($selector);
        }
        return parent::createFromSelector($selector, $constructorArguments);
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $aliasOrClassOrPath
     * @return CommunicationChannelInterface
     */
    public static function createChannelFromString(WorkbenchInterface $workbench, string $alias) : CommunicationChannelInterface
    {
        $selector = new CommunicationChannelSelector($workbench, $alias);
        return $workbench->model()->getModelLoader()->loadCommunicationChannel($selector);
    }
    
    /**
     * 
     * @param CommunicationChannelSelectorInterface $selector
     * @return CommunicationChannelInterface
     */
    public static function createChannelEmpty(CommunicationChannelSelectorInterface $selector) : CommunicationChannelInterface
    {
        return new CommunicationChannel($selector);
    }

    /**
     * 
     * @param string $name
     * @param string $prototype
     * @param UxonObject $uxon
     * @param WorkbenchInterface $workbench
     */
    public static function createChannelFromUxon(string $prototype, UxonObject $uxon, WorkbenchInterface $workbench) : CommunicationChannelInterface
    {
        $channel = self::createChannelFromString($workbench, $prototype);
        $channel->importUxonObject($uxon);
        return $channel;
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param CommunicationMessageSelectorInterface|string $prototype
     * @return CommunicationMessageInterface
     */
    public static function createMessageFromPrototype(WorkbenchInterface $workbench, $prototype, UxonObject $uxon = null) : CommunicationMessageInterface
    {
        if ($prototype instanceof CommunicationMessageSelectorInterface) {
            $selector = $prototype;
        } else {
            $selector = new CommunicationMessageSelector($workbench, $prototype);
        }
        return parent::createFromSelector($selector, [$workbench, $uxon ?? new UxonObject()]);
    }
    
    /**
     * 
     * @param string $text
     * @param string $subject
     * @return CommunicationMessageInterface
     */
    public static function createSimpleMessage(string $text) : CommunicationMessageInterface
    {
        return new TextMessage(new UxonObject([
            'text' => $text
        ]));
    }
}