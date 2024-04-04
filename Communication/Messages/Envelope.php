<?php
namespace exface\Core\Communication\Messages;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\DataTypes\StringDataType;

/**
 * A special type of message that allows any properties - used as generic message container.
 * 
 * For example, the NotifyingBehavior instantiates enevelopes for its messages and passes them to the Communicator
 * without bothering about the exact message type. Each CommunicationChannel then takes care of transforming
 * the envelope to its proper message type.
 * 
 * @author Andrej Kabachnik
 *
 */
class Envelope extends TextMessage
{    
    private $payloadUxon = null;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param UxonObject $uxon
     */
    public function __construct(WorkbenchInterface $workbench, UxonObject $uxon)
    {
        $this->payloadUxon = $uxon->copy();
        foreach (array_keys($uxon->getPropertiesAll()) as $var) {
            $setterCamelCased = 'set' . StringDataType::convertCaseUnderscoreToPascal($var);
            if (! method_exists($this, $setterCamelCased)) {
                $uxon->unsetProperty($var);
            } 
        }
        parent::__construct($workbench, $uxon);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $envelopeUxon = parent::exportUxonObject();
        return $this->payloadUxon->extend($envelopeUxon);
    }
}