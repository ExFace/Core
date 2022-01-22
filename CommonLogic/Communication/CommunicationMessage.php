<?php
namespace exface\Core\Communication\Messages;

use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;

class CommunicationMessage implements CommunicationMessageInterface
{
    use ImportUxonObjectTrait;
    
    private $subject = null;
    
    private $text = null;
    
    private $optionsUxon = null;
    
    public function __construct(string $text, string $subject = null, UxonObject $options = null)
    {
        $this->optionsUxon = $options;
        $this->subject = $subject;
        $this->text = $text;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationMessageInterface::getSubject()
     */
    public function getSubject(): ?string
    {
        return $this->subject;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationMessageInterface::getText()
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationMessageInterface::getOptionsUxon()
     */
    public function getOptionsUxon(): UxonObject
    {
        return $this->optionsUxon ?? new UxonObject();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        // TODO
        return new UxonObject();
    }
}