<?php
namespace exface\Core\Communication\Messages;

use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\CommonLogic\Communication\AbstractMessage;

class TextMessage extends AbstractMessage
{
    private $text = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationMessageInterface::getText()
     */
    public function getText(): string
    {
        return $this->text ?? '';
    }
    
    /**
     * The text to be sent
     * 
     * @uxon-property text
     * @uxon-type string
     * 
     * @param string $value
     * @return CommunicationMessageInterface
     */
    public function setText(string $value) : CommunicationMessageInterface
    {
        $this->text = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        if ($this->text !== null) {
            $uxon->setProperty('text', $this->text);
        }
        return $uxon;
    }
}