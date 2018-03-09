<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\TaskResultFileInterface;
use exface\Core\Interfaces\Tasks\TaskResultStreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Task result containing a file.
 * 
 * @author Andrej Kabachnik
 *
 */
class TaskResultFile extends TaskResultMessage implements TaskResultFileInterface
{
    private $downloadable = false;
    
    private $pathAbsolute = '';
    
    private $mimeType = null;
    
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
     * @see \exface\Core\Interfaces\Tasks\TaskResultFileInterface::isDownloadable()
     */
    public function isDownloadable(): bool
    {
        return $this->downloadable;
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultStreamInterface::getMimeType()
     */
    public function getMimeType(): string
    {
        if (is_null($this->mimeType)) {
            return GuzzleHttp\Psr7\mimetype_from_filename($this->getPathAbsolute());
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