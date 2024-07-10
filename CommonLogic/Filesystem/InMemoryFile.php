<?php
namespace exface\Core\CommonLogic\Filesystem;

use exface\Core\Interfaces\Filesystem\FileInfoInterface;
use exface\Core\Interfaces\Filesystem\FileInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\MimeTypeDataType;

/**
 * An instance of FileInfoInterface and FileInterface, that can be quickly created from variables
 * without actually having a real file.
 * 
 * @author Andrej Kabachnik
 * 
 */
class InMemoryFile implements FileInterface, FileInfoInterface
{
    const SLASH = '/';
    
    private $contents = null;
    
    private $path = null;
    
    private $mimeType = null;
    
    /**
     * 
     * @param DataSourceFileInfo $fileInfo
     */
    public function __construct($contents, string $path, string $mimeType = null)
    {
        $this->contents = $contents;
        $this->path = $path;
        $this->mimeType = $mimeType;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::__toString()
     */
    public function __toString() 
    {
        return $this->path;
    }
    
    /**
     * 
     * @return mixed|NULL
     */
    public function read() : string
    {
        return $this->contents;
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
        $this->contents = $stringOrBinary;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInterface::getFileInfo()
     */
    public function getFileInfo(): FileInfoInterface
    {
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInterface::readLine()
     */
    public function readLine(int $lineNo): ?string
    {
        $text = $this->read();
        $lines = StringDataType::splitLines($text, $lineNo);
        return $lines[$lineNo-1];
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getFolder()
     */
    public function getFolderName() : ?string
    {
        return FilePathDataType::findFolder($this->getPath());
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getFolderPath()
     */
    public function getFolderPath() : ?string
    {
        return FilePathDataType::findFolderPath($this->getPath());
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getFilename()
     */
    public function getFilename(bool $withExtension = true) : string
    {
        return FilePathDataType::findFileName($this->path, $withExtension);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getExtension()
     */
    public function getExtension() : string
    {
        return FilePathDataType::findExtension($this->path) ?? '';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getPath()
     */
    public function getPath() : string
    {
        return $this->path;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getSize()
     */
    public function getSize() : ?int
    {
        // TODO how to get the length of a binary?
        return mb_strlen($this->contents);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getMTime()
     */
    public function getMTime() : ?int
    {
        return null;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getCTime()
     */
    public function getCTime() : ?int
    {
        return null;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::isWritable()
     */
    public function isWritable() : bool
    {
        return false;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::isReadable()
     */
    public function isReadable() : bool
    {
        return true;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::isFile()
     */
    public function isFile() : bool
    {
        return true;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::isDir()
     */
    public function isDir() : bool
    {
        return false;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::isLink()
     */
    public function isLink() : bool
    {
        return false;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getLinkTarget()
     */
    public function getLinkTarget() : ?string
    {
        return null;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::openFile()
     */
    public function openFile(string $mode = null) : FileInterface
    {
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getModifiedOn()
     */
    public function getModifiedOn(): ?\DateTimeInterface
    {
        return null;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getCreatedOn()
     */
    public function getCreatedOn(): ?\DateTimeInterface
    {
        return null;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getFolderInfo()
     */
    public function getFolderInfo(): ?FileInfoInterface
    {
        return null;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getBasePath()
     */
    public function getBasePath(): ?string
    {
        return null;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getPathAbsolute()
     */
    public function getPathAbsolute(): string
    {
        return $this->getPath();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getPathRelative()
     */
    public function getPathRelative(): ?string
    {
        return null;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::isPathAbsolute()
     */
    public function isPathAbsolute(): bool
    {
        return true;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getDirectorySeparator()
     */
    public function getDirectorySeparator(): string
    {
        return self::SLASH;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getMimetype()
     */
    public function getMimetype(): ?string
    {
        if ($this->mimeType === null) {
            $this->mimeType = MimeTypeDataType::guessMimeTypeOfExtension($this->getExtension(), 'text/plain');
        }
        return $this->mimeType;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getType()
     */
    public function getType(): string
    {
        return FileInfoInterface::FILE;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::exists()
     */
    public function exists(): bool
    {
        return true;
    }
}