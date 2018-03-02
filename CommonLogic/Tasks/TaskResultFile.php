<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\TaskResultFileInterface;
use exface\Core\Interfaces\Tasks\TaskResultUriInterface;
use Psr\Http\Message\UriInterface;

/**
 * Task result containing a downloadable file: i.e. text, code, etc..
 * 
 * @author Andrej Kabachnik
 *
 */
class TaskResultFile extends TaskResultMessage implements TaskResultFileInterface
{
    private $uri = null;
    
    /**
     * 
     * @param TaskInterface $task
     * @param WidgetInterface $widget
     */
    public function __construct(TaskInterface $task, UriInterface $download = null)
    {
        parent::__construct($task);
        $this->setDownloadUri($download);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultFileInterface::setDownloadUri()
     */
    public function setDownloadUri(UriInterface $uri): TaskResultUriInterface
    {
        $this->uri = $uri;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultFileInterface::getDownloadUri()
     */
    public function getDownloadUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultFileInterface::hasDownload()
     */
    public function hasDownload(): bool
    {
        return is_null($this->uri) ? false : true;
    }

    
}