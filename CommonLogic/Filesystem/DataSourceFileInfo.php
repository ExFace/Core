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
class DataSourceFileInfo implements FileInfoInterface
{
    const SCHEME = 'metamodel://';
    
    const SLASH = '/';
    
    private $pathname = null;
    
    private $folder = null;
    
    private $filenameMask = null;
    
    private $filename = null;
    
    private $object = null;
    
    private $fileBehavior = null;
    
    private $fileAttributes = [];
    
    private $fileData = null;
    
    private $fileClass = null;
    
    private $infoClass = null;
    
    /**
     * 
     * @param string $folder
     * @param MetaObjectInterface $object
     */
    public function __construct($path, WorkbenchInterface $workbench)
    {
        $matches = [];
        preg_match('@' . self::SCHEME . '([^/]+)/([^/]+)/?(.*)@i', $path, $matches);
        $objectSelector = $matches[1] ?? null;
        $folder = $matches[2] ?? null;
        $filename = $matches[3] ?? null;
        if ($folder === null || $objectSelector === null) {
            throw new UnexpectedValueException('Cannot parse virtual file path "' . $path . '".');
        }
        $this->pathname = $path;
        $this->filenameMask = $filename;
        $this->folder = $folder;
        $this->object = MetaObjectFactory::createFromString($workbench, $objectSelector);
    }
    
    /**
     * 
     * @return MetaObjectInterface
     */
    public function getMetaObject() : MetaObjectInterface
    {
        return $this->object;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getFolder()
     */
    public function getFolder() : ?string
    {
        return $this->folder;
    }
    
    /**
     * Returns the filename mask from the original virtual path.
     * 
     * TODO add support for real masking with wildcards. Currently the mask can either be `*` or a
     * plain filename (without a wildcard).
     * 
     * @return string|NULL
     */
    protected function getFilenameMask() : ?string
    {
        return $this->filenameMask;
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasFilenameMask() : bool
    {
        return $this->filenameMask !== null && $this->filenameMask !== '' && $this->filenameMask !== '*';
    }
    
    /**
     * 
     * @throws InvalidArgumentException
     * @return FileBehavior
     */
    protected function getFileBehavior() : FileBehavior
    {
        if ($this->fileBehavior === null) {
            $behaviors = $this->getMetaObject()->getBehaviors()->getByPrototypeClass(FileBehavior::class);
            if (! $behaviors->isEmpty()) {
                $this->fileBehavior = $behaviors->getFirst();
            } else {
                throw new InvalidArgumentException('Cannot use object "' . $this->getMetaObject() . '" as a file: missing FileBehavior!');
            }
        }
        return $this->fileBehavior;
    }
    
    /**
     * 
     * @return bool
     */
    public function isExisting() : bool
    {
        return $this->getFileDataSheet()->countRows() > 0;
    }
        
    /**
     * 
     * @return DataSheetInterface
     */
    protected function getFileDataSheet() : DataSheetInterface
    {
        if ($this->fileData === null) {
            $fileBeh = $this->getFileBehavior();
            
            $this->fileData = DataSheetFactory::createFromObject($this->getMetaObject());
            $this->fileAttributes = $fileBeh->getFileAttributes();
            foreach ($this->fileAttributes as $attr) {
                $this->fileData->getColumns()->addFromAttribute($attr);
            }
            
            $this->fileData->getFilters()->addConditionFromAttribute($fileBeh->getFolderAttribute(), $this->getFolder(), ComparatorDataType::EQUALS);
            if ($this->hasFilenameMask() && $filenameAttr = $this->getFileBehavior()->getFilenameAttribute()) {
                $this->fileData->getFilters()->addConditionFromAttribute($filenameAttr, $this->getFilenameMask(), ComparatorDataType::EQUALS);
            }
            
            $this->fileData->dataRead();
            
            if ($this->fileData->countRows() > 1) {
                throw new OverflowException('Ambiguous virtual file path "' . $this->getPathname() . '": ' . $this->fileData->countRows() . ' matching files found!');
            }
        }
        return $this->fileData;
    }
    
    /**
     * 
     * @return mixed|NULL
     */
    public function getContent()
    {
        if (null !== $attr = $this->getFileBehavior()->getContentsAttribute()) {
            $val = $this->getFileDataSheet()->getColumns()->getByAttribute($attr)->getValue(0);
            $dataType = $attr->getDataType();
            if ($dataType instanceof BinaryDataType) {
                switch (true) {
                    case $dataType->getEncoding() === BinaryDataType::ENCODING_BASE64:
                        $val = BinaryDataType::convertBase64ToBinary($val);
                        break;
                    case $dataType->getEncoding() === BinaryDataType::ENCODING_HEX:
                        $val = BinaryDataType::convertHexToBinary($val);
                        break;
                }
            }
            return $val;
        }
        return null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getFilename()
     */
    public function getFilename(bool $withExtension = true) : string
    {
        if ($this->filename === null) {
            if (null !== $attr = $this->getFileBehavior()->getFilenameAttribute()) {
                $this->filename = $this->getFileDataSheet()->getColumns()->getByAttribute($attr)->getValue(0);
            } elseif ($this->hasFilenameMask()) {
                $this->filename = $this->getFilenameMask();
            }
        }
        if ($withExtension === false && $this->filename !== null) {
            return StringDataType::substringBefore($this->filename, FilePathDataType::findFileName($this->filename, false));
        }
        return $this->filename ?? '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getExtension()
     */
    public function getExtension() : string
    {
        return FilePathDataType::findExtension($this->getFilename());    
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getPath()
     */
    public function getPath(bool $withFilename = true) : string
    {
        if ($withFilename === false) {
            return FilePathDataType::findFolderPath($this->pathname);
        }
        return $this->pathname;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getSize()
     */
    public function getSize() : ?int
    {
        if (null !== $attr = $this->getFileBehavior()->getFileSizeAttribute()) {
            return $this->getFileDataSheet()->getColumns()->getByAttribute($attr)->getValue(0);
        }
        return null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getMTime()
     */
    public function getMTime() : ?int
    {
        if (null !== $attr = $this->getFileBehavior()->getTimeModifiedAttribute()) {
            return $this->getFileDataSheet()->getColumns()->getByAttribute($attr)->getValue(0);
        }
        return null;
        
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getCTime()
     */
    public function getCTime() : ?int
    {
        if (null !== $attr = $this->getFileBehavior()->getTimeCreatedAttribute()) {
            return $this->getFileDataSheet()->getColumns()->getByAttribute($attr)->getValue(0);
        }
        return null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::isWritable()
     */
    public function isWritable() : bool
    {
        return $this->getMetaObject()->isWritable();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::isReadable()
     */
    public function isReadable() : bool
    {
        return $this->getMetaObject()->isReadable();
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
        return new DataSourceFileInfo($folderPath, $this->object->getWorkbench());
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
    public function getPathAbsolute(bool $withFilename = true): string
    {
        return $this->getPath($withFilename);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getPathRelative()
     */
    public function getPathRelative(bool $withFilename = true): ?string
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
}