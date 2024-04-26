<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\Tasks\ResultFileInterface;
use exface\Core\Interfaces\Tasks\ResultStreamInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\FileNotReadableError;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\MimeTypeDataType;

/**
 * Task result containing a file.
 * 
 * @author Andrej Kabachnik
 *
 */
class ResultFile extends ResultMessage implements ResultFileInterface
{
    private $downloadable = false;
    
    private $pathAbsolute = '';
    
    private $mimeType = null;

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
    
    public function setPath(string $path): ResultFileInterface
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
     * @see \exface\Core\Interfaces\Tasks\ResultFileInterface::getFilename()
     */
    public function getFilename() : string
    {
        return FilePathDataType::findFileName($this->getPathAbsolute());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultFileInterface::getPathAbsolute()
     */
    public function getPathAbsolute(): string
    {
        return $this->pathAbsolute;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultStreamInterface::getMimeType()
     */
    public function getMimeType(): string
    {
        if (is_null($this->mimeType)) {
            return MimeTypeDataType::findMimeTypeOfFile($this->getPathAbsolute());
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultFileInterface::getResourceHandle()
     */
    public function getResourceHandle(string $mode = "r")
    {
        $handle = fopen($this->getPathAbsolute(), $mode);
        if ($handle === false) {
            throw new RuntimeException('Cannot read action result "' . $this->getPathAbsolute() . '"!');
        }
        return $handle;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Tasks\ResultMessage::isEmpty()
     */
    public function isEmpty() : bool
    {
        return parent::isEmpty() && ! $this->getPathAbsolute();
    }
}