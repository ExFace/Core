<?php
namespace exface\Core\CommonLogic\Filesystem;

use exface\Core\DataTypes\FilePathDataType;
use \DateTimeInterface;
use exface\Core\Interfaces\Filesystem\FileInfoInterface;
use exface\Core\Interfaces\Filesystem\FileInterface;
use exface\Core\DataTypes\MimeTypeDataType;

/**
 * Contains information about a single local file - similar to PHPs splFileInfo.
 * 
 * @author Andrej Kabachnik
 */
class LocalFileInfo implements FileInfoInterface
{
    private $splFileInfo = null;
    
    private $basePath = null;
    
    private $directorySeparator = null;
    
    /**
     * Global static cache for normalized paths: $normalized[$path][$dirSepararator] = $normalizedPath
     * 
     * Since all local paths work similarly, it makes sense to use a static cache accross
     * all FileInfo classes! This will particularly speed up normalization of common base paths.
     * 
     * @var string[][]
     */
    protected static $normalized = [];
    
    /**
     * 
     * @param string|\SplFileInfo $pathOrSplFileInfo
     * @param string $basePath
     * @param string $directorySeparator
     */
    public function __construct($pathOrSplFileInfo, string $basePath = null, string $directorySeparator = '/')
    {
        if ($pathOrSplFileInfo instanceof \SplFileInfo) {
            $this->splFileInfo = $pathOrSplFileInfo;
        } else {
            $this->splFileInfo = new \SplFileInfo($pathOrSplFileInfo);
        }
        
        $this->directorySeparator = $directorySeparator;        
        $this->basePath = $basePath;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getFolder()
     */
    public function getFolderName() : ?string
    {
        return FilePathDataType::findFolder($this->splFileInfo->getPathname());
    }
    
    public function getFolderPath() : ?string
    {
        return $this->splFileInfo->getPath();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getFilename()
     */
    public function getFilename(bool $withExtension = true) : string
    {
        if ($withExtension === false) {
            return FilePathDataType::findFileName($this->splFileInfo->getFilename(), false);
        }
        return $this->splFileInfo->getFilename();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getExtension()
     */
    public function getExtension() : string
    {
        return $this->splFileInfo->getExtension();    
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getPath()
     */
    public function getPath() : string
    {
        return $this->normalize($this->splFileInfo->getPathname());
    }
    
    /**
     * 
     * @param string $path
     * @return string
     */
    protected function normalize(string $path) : string
    {
        $normalized = static::$normalized[$path][$this->directorySeparator] ?? null;
        if (null === $normalized) {
            $normalized = FilePathDataType::normalize($path, $this->directorySeparator);
            static::$normalized[$path][$this->directorySeparator] = $normalized;
        }
        return $normalized;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getBasePath()
     */
    public function getBasePath() : ?string
    {
        if ($this->basePath === null) {
            return null;
        }
        return $this->normalize($this->basePath);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::isPathAbsolute()
     */
    public function isPathAbsolute() : bool
    {
        return FilePathDataType::isAbsolute($this->splFileInfo->getPathname());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getPathAbsolute()
     */
    public function getPathAbsolute() : string
    {
        $path = $this->getPath();
        if ($this->isPathAbsolute()) {
            return $path;
        }
        return FilePathDataType::join([$this->getBasePath(), $path]);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getPathRelative()
     */
    public function getPathRelative() : ?string
    {
        $basePath = $this->getBasePath() ? $this->getBasePath() . $this->directorySeparator : '';
        return $basePath !== '' ? str_replace($basePath, '', $this->getPath()) : $this->getPath();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getSize()
     */
    public function getSize() : ?int
    {
        return $this->splFileInfo->getSize();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getMTime()
     */
    public function getMTime() : ?int
    {
        return $this->splFileInfo->getMTime();
        
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getCTime()
     */
    public function getCTime() : ?int
    {
        return $this->splFileInfo->getCTime();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::isWritable()
     */
    public function isWritable() : bool
    {
        return $this->splFileInfo->isWritable();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::isReadable()
     */
    public function isReadable() : bool
    {
        return $this->splFileInfo->isReadable();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::isFile()
     */
    public function isFile() : bool
    {
        return $this->splFileInfo->isFile();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::isDir()
     */
    public function isDir() : bool
    {
        return $this->splFileInfo->isDir();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::isLink()
     */
    public function isLink() : bool
    {
        return $this->splFileInfo->isLink();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getLinkTarget()
     */
    public function getLinkTarget() : ?string
    {
        return $this->splFileInfo->getLinkTarget();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::openFile()
     */
    public function openFile(string $mode = null) : FileInterface
    {
        return new LocalFile($this, $mode);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::__toString()
     */
    public function __toString() 
    {
        return $this->getPath();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getModifiedOn()
     */
    public function getModifiedOn(): ?DateTimeInterface
    {
        return new \DateTimeImmutable('@' . $this->getMTime());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getCreatedOn()
     */
    public function getCreatedOn(): ?\DateTimeInterface
    {
        return new \DateTimeImmutable('@' . $this->getCTime());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getFolderInfo()
     */
    public function getFolderInfo(): ?FileInfoInterface
    {
        $folderPath = $this->getFolderPath();
        if ($folderPath === null || $folderPath === '') {
            return null;
        }
        return new LocalFileInfo($folderPath, $this->getBasePath(), $this->getDirectorySeparator());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getDirectorySeparator()
     */
    public function getDirectorySeparator() : string
    {
        return $this->directorySeparator;
    }
    
    /**
     * 
     * @param string $value
     * @return LocalFileInfo
     */
    public function setDirectorySeparator(string $value) : LocalFileInfo
    {
        $this->directorySeparator = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getMimetype()
     */
    public function getMimetype(): ?string
    {
        return MimeTypeDataType::findMimeTypeOfFile($this->getPathAbsolute());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getType()
     */
    public function getType(): string
    {
        $this->splFileInfo->getType();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::exists()
     */
    public function exists(): bool
    {
        return file_exists($this->getPathAbsolute());
    }
}