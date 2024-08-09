<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\Tasks\ResultFileInterface;
use exface\Core\Interfaces\Tasks\ResultStreamInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Filesystem\FileInfoInterface;
use exface\Core\Exceptions\FileNotReadableError;
use exface\Core\Exceptions\RuntimeException;

/**
 * Task result containing a file.
 * 
 * @author Andrej Kabachnik
 *
 */
class ResultFile extends ResultMessage implements ResultFileInterface
{
    private $downloadable = false;
    
    private $fileInfo = null;
    
    private $mimeType = null;
    
    public function __construct(TaskInterface $task, FileInfoInterface $fileInfo = null)
    {
        parent::__construct($task);
        $this->fileInfo = $fileInfo;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultFileInterface::isDownloadable()
     */
    public function isDownloadable(): bool
    {
        return $this->downloadable;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultFileInterface::setDownloadable()
     */
    public function setDownloadable(bool $trueOrFalse) : ResultFileInterface
    {
        $this->downloadable = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultFileInterface::getFileInfo()
     */
    public function getFileInfo() : FileInfoInterface
    {
        return $this->fileInfo;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultStreamInterface::getMimeType()
     */
    public function getMimeType(): string
    {
        return $this->mimeType ?? $this->fileInfo->getMimetype();
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
        return parent::isEmpty() && $this->fileInfo === null;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultFileInterface::getContents()
     */
    public function getContents() : string
    {
        $result = file_get_contents($this->getPathAbsolute());
        if ($result === false) {
            throw new FileNotReadableError('Cannot read file "' . $this->getPathAbsolute() . '"!');
        }
        if ($result === false) {
            throw new RuntimeException('Cannot read action result "' . $this->getPathAbsolute() . '"!');
        }
        return $result;
    }
}