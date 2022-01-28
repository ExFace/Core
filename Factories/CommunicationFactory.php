<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Communication\CommunicationChannelInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Selectors\CommunicationChannelSelector;
use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\CommonLogic\Selectors\CommunicationMessageSelector;
use exface\Core\Communication\Messages\GenericMessage;

/**
 * Produces components related to the communication framework
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class CommunicationFactory extends AbstractSelectableComponentFactory
{

    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $aliasOrClassOrPath
     * @return CommunicationChannelInterface
     */
    public static function createChannelFromString(WorkbenchInterface $workbench, string $aliasOrClassOrPath) : CommunicationChannelInterface
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
    public static function createChannelFromUxon(string $prototype, UxonObject $uxon, WorkbenchInterface $workbench) : CommunicationChannelInterface
    {
        $channel = self::createChannelFromString($workbench, $prototype);
        $channel->importUxonObject($uxon);
        return $channel;
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $prototype
     * @return CommunicationMessageInterface
     */
    public static function createMessageFromPrototype(WorkbenchInterface $workbench, string $prototype) : CommunicationMessageInterface
    {
        $selector = new CommunicationMessageSelector($workbench, $prototype);
        return parent::createFromSelector($selector);
    }
    
    /**
     * 
     * @param string $text
     * @param string $subject
     * @return CommunicationMessageInterface
     */
    public static function createMessage(string $text, string $subject = null) : CommunicationMessageInterface
    {
        $msg = new GenericMessage(new UxonObject([
            'text' => $subject
        ]));
        if ($subject !== null) {
            $msg->setSubject($subject);
        }
        return $msg;
    }
}