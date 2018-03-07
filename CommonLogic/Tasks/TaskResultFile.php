<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\TaskResultFileInterface;
use exface\Core\Interfaces\Tasks\TaskResultUriInterface;
use Psr\Http\Message\UriInterface;
use GuzzleHttp\Psr7\Uri;

/**
 * Task result containing a file.
 * 
 * @author Andrej Kabachnik
 *
 */
class TaskResultFile extends TaskResultMessage implements TaskResultFileInterface
{
    private $uri = null;
    
    private $pathAbsolute = '';
    
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
        if (is_null($this->uri)) {
            $url = $this->getWorkbench()->getCMS()->createLinkToFile($this->getPathAbsolute());
            $this->uri = new Uri($url);
        }
        return $this->uri;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultFileInterface::hasDownload()
     */
    public function hasDownload(): bool
    {
        return is_null($this->uri) && is_null($this->pathAbsolute) ? false : true;
    }
    
    public function setPath(string $path): TaskResultFileInterface
    {
        $filemanager = $this->getWorkbench()->filemanager();
        if ($filemanager::pathIsAbsolute($path)) {
            $path = $path;
        } else {
            $path = $filemanager::pathJoin([$filemanager::getPathToBaseFolder(), $path]);
        }
        $this->pathAbsolute = $filemanager::pathNormalize($path);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultFileInterface::getPathAbsolute()
     */
    public function getPathAbsolute(): string
    {
        return $this->pathAbsolute;
    }
}