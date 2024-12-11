<?php
namespace exface\Core\CommonLogic\Filesystem;

use exface\Core\Exceptions\FileNotWritableError;
use exface\Core\Interfaces\Filesystem\FileInfoInterface;
use exface\Core\Interfaces\Filesystem\FileInterface;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Exceptions\FileNotReadableError;
use exface\Core\Interfaces\Filesystem\FileStreamInterface;

/**
 * 
 * 
 * @author Andrej Kabachnik
 * 
 */
class LocalFile implements FileInterface, FileStreamInterface
{
    private $fileInfo = null;
    
    private $mode = null;
    
    private $splFileObject = null;
    
    /**
     * 
     * @param LocalFileInfo $fileInfo
     * @param string $mode
     */
    public function __construct(LocalFileInfo $fileInfo, string $mode = null)
    {
        $this->fileInfo = $fileInfo;
        $this->mode = $mode ?? 'r+';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::__toString()
     */
    public function __toString() 
    {
        return $this->fileInfo->__toString();
    }
    
    /**
     * 
     * @return mixed|null
     */
    public function read() : string
    {
        $path = $this->getFileInfo()->getPathAbsolute();
        $result = file_get_contents($path);
        if ($result === false) {
            throw new FileNotReadableError('Cannot read file "' . $path . '"!', null, null, $this->getFileInfo());
        }
        return $result;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInterface::readStream()
     */
    public function readStream()
    {
        $path = $this->fileInfo->getPathAbsolute();
        $result = fopen($path, $this->mode);
        if ($result === false) {
            throw new FileNotReadableError('Cannot read file "' . $path . '"!', null, null, $this->getFileInfo());
        }
        return $result;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInterface::writeStream()
     */
    public function writeStream($resource): FileInterface
    {
        // TODO really write stream here instead of treating it as a string
        return $this->write($resource);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInterface::write()
     */
    public function write($stringOrBinary): FileInterface
    {
        $path = $this->fileInfo->getPathAbsolute();
        $dir = $this->fileInfo->getFolderPath();
        if (!is_dir($dir)) {
            Filemanager::pathConstruct($dir);
        }
        $result = file_put_contents($path, $stringOrBinary);
        if ($result === false) {
            throw new FileNotWritableError('Cannot write "' . $path . '"', null, null, $this->fileInfo);
        }
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInterface::getFileInfo()
     */
    public function getFileInfo(): FileInfoInterface
    {
        return $this->fileInfo;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInterface::readLine()
     */
    public function readLine(int $lineNo) : ?string
    {
        if ($lineNo === 1) {
            $value = $this->getSplFileObject()->fgets();
        } else {
            $fileObject = $this->getSplFileObject();
            $fileObject->seek(($lineNo-1));
            $value = $fileObject->current();
        }
        return $value;
    }
    
    /**
     * 
     * @return \SplFileObject
     */
    protected function getSplFileObject() : \SplFileObject
    {
        if ($this->splFileObject === null) {
            $this->splFileObject = new \SplFileObject($this->fileInfo->getPathAbsolute(), $this->mode);
        }
        return $this->splFileObject;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileStreamInterface::getStreamUrl()
     */
    public function getStreamUrl() : string
    {
        return $this->fileInfo->getPathAbsolute();
    }
}