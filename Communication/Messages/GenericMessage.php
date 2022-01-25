<?php
namespace exface\Core\Communication\Messages;

use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;

class GenericMessage implements CommunicationMessageInterface
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
    
    public function setSubject(string $value) : CommunicationMessageInterface
    {
        $this->subject = $value;
        return $this;
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
    
    public function setText(string $value) : CommunicationMessageInterface
    {
        $this->text = $value;
        return $this;
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
    
    protected function setOptions(UxonObject $uxon) : CommunicationMessageInterface
    {
        $this->optionsUxon = $uxon;
        return $this;
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