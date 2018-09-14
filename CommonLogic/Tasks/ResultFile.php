<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\Tasks\ResultFileInterface;
use exface\Core\Interfaces\Tasks\ResultStreamInterface;
use exface\Core\DataTypes\BooleanDataType;

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
            return GuzzleHttp\Psr7\mimetype_from_filename($this->getPathAbsolute());
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
        return parent::isEmpty() && ! $this->getPathAbsolute();
    }


}