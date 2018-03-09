<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\TaskResultTextContentInterface;
use exface\Core\Interfaces\Tasks\TaskResultStreamInterface;

/**
 * Task result containing textual content: i.e. text, code, etc..
 * 
 * @author Andrej Kabachnik
 *
 */
class TaskResultTextContent extends TaskResultMessage implements TaskResultTextContentInterface
{
    private $content = null;
    
    private $mimeType = null;
    
    /**
     * 
     * @param TaskInterface $task
     * @param WidgetInterface $widget
     */
    public function __construct(TaskInterface $task, string $content = null)
    {
        parent::__construct($task);
        $this->setContent($content);
    }
    
    public function setContent(string $content): TaskResultTextContentInterface
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
     * @see \exface\Core\Interfaces\Tasks\TaskResultStreamInterface::getMimeType()
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
     * @see \exface\Core\Interfaces\Tasks\TaskResultStreamInterface::setMimeType()
     */
    public function setMimeType(string $string): TaskResultStreamInterface
    {
        $this->mimeType = $string;
        return $this;
    }
}