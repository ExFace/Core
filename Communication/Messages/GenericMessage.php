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
    
    public function __construct(UxonObject $uxon)
    {
        $this->importUxonObject($uxon);
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
     * @param string $value
     * @return CommunicationMessageInterface
     */
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
    
    /**
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
        $uxon = new UxonObject([
            'text' => $this->getText()
        ]);
        
        if ($this->getSubject() !== null) {
            $uxon->setProperty('subject', $this->getSubject());
        }        
        return $this;
    }
}