<?php
namespace exface\Core\CommonLogic\Filesystem;

use exface\Core\Behaviors\FileBehavior;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\BinaryDataType;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\OverflowException;
use \DateTimeInterface;
use exface\Core\Interfaces\Filesystem\FileInfoInterface;
use exface\Core\Interfaces\Filesystem\FileInterface;

/**
 * Allows to work with files stored in data sources if the meta object has the `FileBehavior`.
 * 
 * The paths have the following schemes: 
 * 
 * - `metamodel://my.app.ObjectAlias/uid_of_file/filename.ext`
 * - `metamodel://my.app.ObjectAlias/uid_of_file/*`
 * - `metamodel://my.app.ObjectAlias/folder_attribute/*`
 * 
 * Currently no real (nested) folder structure is supported - you can't travel up the folder tree, but
 * the `folder_attribute` of the `FileBehavior` may contain a complex path.
 * 
 * @author Andrej Kabachnik
 * 
 * IDEA added wildcard support for filenames - to select one of multiple files inside a folder in
 * case the `folder_attribute` is not a UID. See `getFilenameMask()` for details
 */
class LocalFileInfo implements FileInfoInterface
{
    private $splFileInfo = null;
    
    /**
     * 
     * @param string $folder
     * @param MetaObjectInterface $object
     */
    public function __construct($pathOrSplFileInfo)
    {
        if ($pathOrSplFileInfo instanceof \SplFileInfo) {
            $this->splFileInfo = $pathOrSplFileInfo;
        } else {
            $this->splFileInfo = new \SplFileInfo($pathOrSplFileInfo);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getFolder()
     */
    public function getFolder() : ?string
    {
        return FilePathDataType::findFolder($this->splFileInfo->getPathname());
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
    public function getPath(bool $withFilename = true) : string
    {
        return $withFilename === true ? $this->splFileInfo->getPathname() : $this->splFileInfo->getPath();
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
    public function openFile(string $open_mode = null, bool $use_include_path = null) : FileInterface
    {
        return new DataSourceFile($this);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::__toString()
     */
    public function __toString() 
    {
        return $this->getPathname();
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
        $folderPath = $this->getPath(false);
        if ($folderPath === null || $folderPath === '') {
            return null;
        }
        return new LocalFileInfo($folderPath, $this->object->getWorkbench());
    }
}