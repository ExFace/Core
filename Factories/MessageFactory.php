<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Model\MessageInterface;
use exface\Core\CommonLogic\Model\Message;

/**
 * Instantiates messages
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class MessageFactory extends AbstractStaticFactory
{
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $messageCode
     * @return MessageInterface
     */
    public static function createFromCode(WorkbenchInterface $workbench, string $messageCode) : MessageInterface
    {
        return new Message($workbench, $messageCode);
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $title
     * @param string $hint
     * @param string $description
     * @return MessageInterface
     */
    public static function createError(WorkbenchInterface $workbench, string $title, string $hint = '', string $description = '') : MessageInterface
    {
        $msg = new Message($workbench, '');
        $msg->setTitle($title);
        if ($hint !== null) {
            $msg->setHint($hint);
        }
        if ($description !== null) {
            $msg->setDescription($description);
        }
        return $msg;
    }
}