<?php
namespace exface\Core\CommonLogic\Filesystem;

use exface\Core\Interfaces\Filesystem\FileInfoInterface;
use exface\Core\Interfaces\Filesystem\FileInterface;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Exceptions\FileNotReadableError;

/**
 * 
 * 
 * @author Andrej Kabachnik
 * 
 */
class LocalFile implements FileInterface
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
     * @return mixed|NULL
     */
    public function read() : string
    {
        $result = file_get_contents($this->getFileInfo()->getPathAbsolute());
        if ($result === false) {
            throw new FileNotReadableError('Cannot read file "' . $this->getPathAbsolute() . '"!', null, null, $this->getFileInfo());
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
        $result = fopen($this->fileInfo->getPath(true), $this->mode);
        if ($result === false) {
            throw new FileNotReadableError('Cannot read file "' . $this->getPathAbsolute() . '"!', null, null, $this->getFileInfo());
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
        return $this->write($resource);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInterface::write()
     */
    public function write($stringOrBinary): FileInterface
    {
        Filemanager::dumpFile($this->fileInfo->getPath(true), $stringOrBinary);
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
}