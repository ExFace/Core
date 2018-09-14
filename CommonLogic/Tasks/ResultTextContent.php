<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\Tasks\ResultTextContentInterface;
use exface\Core\Interfaces\Tasks\ResultStreamInterface;

/**
 * Task result containing textual content: i.e. text, code, etc..
 * 
 * @author Andrej Kabachnik
 *
 */
class ResultTextContent extends ResultMessage implements ResultTextContentInterface
{
    private $content = null;
    
    private $mimeType = null;
    
    public function setContent(string $content): ResultTextContentInterface
    {
        $this->content = $content;
        return $this;
    }

    public function hasContent(): bool
    {
        return is_null($this->content) ? false : true;
    }

    public function getContent(): string
    {
        return $this->content;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultStreamInterface::getMimeType()
     */
    public function getMimeType($default = null): string
    {
        if (is_null($this->mimeType)) {
            return $default;
        }
        return $this->mimeType;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultStreamInterface::setMimeType()
     */
    public function setMimeType(string $string): ResultStreamInterface
    {
        $this->mimeType = $string;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Tasks\ResultMessage::isEmpty()
     */
    public function isEmpty() : bool
    {
        return parent::isEmpty() && $this->getContent() === '';
    }
}