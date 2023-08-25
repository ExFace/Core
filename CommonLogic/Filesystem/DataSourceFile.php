<?php
namespace exface\Core\CommonLogic\Filesystem;

use exface\Core\Interfaces\Filesystem\FileInfoInterface;
use exface\Core\Interfaces\Filesystem\FileInterface;

/**
 * 
 * 
 * @author Andrej Kabachnik
 * 
 */
class DataSourceFile implements FileInterface
{
    private $fileInfo = null;
    
    /**
     * 
     * @param DataSourceFileInfo $fileInfo
     */
    public function __construct(DataSourceFileInfo $fileInfo)
    {
        $this->fileInfo = $fileInfo;
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
        return $this->fileInfo->getContent();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInterface::readStream()
     */
    public function readStream()
    {
        $stream = fopen('php://memory','r+');
        fwrite($stream, $this->read());
        rewind($stream);
        return $stream;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInterface::writeStream()
     */
    public function writeStream($resource): FileInterface
    {
        $this->write(stream_get_contents($resource));
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInterface::write()
     */
    public function write($stringOrBinary): FileInterface
    {
        // TODO
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
}