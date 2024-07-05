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
use exface\Core\Interfaces\Model\Behaviors\FileBehaviorInterface;
use exface\Core\Interfaces\Selectors\MetaObjectSelectorInterface;
use exface\Core\Factories\QueryBuilderFactory;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\DataTypes\DateTimeDataType;

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
    
    private $pathFromConstructor = null;
    
    private $uid = null;
    
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
        if ($filename === '') {
            $filename = null;
        }
        if ($folder === null || $objectSelector === null) {
            throw new UnexpectedValueException('Cannot parse virtual file path "' . $path . '".');
        }
        $this->pathFromConstructor = $path;
        $this->filenameMask = $filename;
        $this->folder = $folder;
        $this->object = MetaObjectFactory::createFromString($workbench, $objectSelector);
    }
    
    /**
     *
     * @param WorkbenchInterface $workbench
     * @param string|MetaObjectSelectorInterface|MetaObjectInterface $objectSelectorString
     * @param string $uid
     * @return DataSourceFileInfo
     */
    public static function fromObjectUID(WorkbenchInterface $workbench, $objectSelectorString, string $uid) : self
    {
        switch (true) {
            case $objectSelectorString instanceof MetaObjectInterface:
                $objectString = $objectSelectorString->getAliasWithNamespace();
                break;
            case $objectSelectorString instanceof MetaObjectSelectorInterface:
                $objectString = $objectSelectorString->toString();
                break;
            case is_string($objectSelectorString):
                $objectString = $objectSelectorString;
                break;
            default:
                throw new InvalidArgumentException('Cannot instantiate file from object "' . $objectSelectorString . "': expecting object selector or object instance!");
        }
        $path = self::SCHEME . $objectString . self::SLASH . $uid;
        return new self($path, $workbench);
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
    public function getFolderName() : ?string
    {
        return $this->folder;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getFolderPath()
     */
    public function getFolderPath() : ?string
    {
        return FilePathDataType::findFolderPath($this->getPathAbsolute());
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
    protected function getFileBehavior() : FileBehaviorInterface
    {
        if ($this->fileBehavior === null) {
            $behaviors = $this->getMetaObject()->getBehaviors()->getByPrototypeClass(FileBehaviorInterface::class);
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
            $query = QueryBuilderFactory::createForObject($fileBeh->getObject());
            $contentAttr = $fileBeh->getContentsAttribute();
            $folderAttr = $fileBeh->getFolderAttribute();
            foreach ($this->fileAttributes as $attr) {
                if (! $query->canReadAttribute($attr)) {
                    continue;
                }
                // NEVER load content right away because it might be large and will slow down getting
                // file information a lot! It can always be loaded explicitly when `getContent()` is called
                if ($attr === $contentAttr) {
                    //continue;
                }
                $this->fileData->getColumns()->addFromAttribute($attr);
            }
            
            if (! $folderAttr->getRelationPath()->isEmpty() && $this->getMetaObject()->hasUidAttribute()) {
                $folderAttr = $this->getMetaObject()->getUidAttribute();
            }
            $this->fileData->getFilters()->addConditionFromAttribute($folderAttr, $this->getFolderName(), ComparatorDataType::EQUALS);
            if ($this->hasFilenameMask() && $filenameAttr = $this->getFileBehavior()->getFilenameAttribute()) {
                $this->fileData->getFilters()->addConditionFromAttribute($filenameAttr, $this->getFilenameMask(), ComparatorDataType::EQUALS);
            }
            
            $this->fileData->dataRead();
            
            if ($this->fileData->countRows() > 1) {
                throw new OverflowException('Ambiguous virtual file path "' . $this->getPath() . '": ' . $this->fileData->countRows() . ' matching files found!');
            }
        }
        return $this->fileData;
    }
    
    protected function getFileDataColumn(MetaAttributeInterface $attribute) : DataColumnInterface
    {
        $fileData = $this->getFileDataSheet();
        if (! $col = $fileData->getColumns()->getByAttribute($attribute)) {
            $col = $fileData->getColumns()->addFromAttribute($attribute);
            $fileData->dataRead();
        }
        return $col;
    }
    
    /**
     * 
     * @return mixed|NULL
     */
    public function getContent()
    {
        if (null !== $attr = $this->getFileBehavior()->getContentsAttribute()) {
            $val = $this->getFileDataColumn($attr)->getValue(0);
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
                $this->filename = $this->getFileDataColumn($attr)->getValue(0);
            } elseif ($this->hasFilenameMask()) {
                $this->filename = $this->getFilenameMask();
            }
        }
        if ($withExtension === false && $this->filename !== null) {
            return FilePathDataType::findFileName($this->filename, false);
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
        return FilePathDataType::findExtension($this->getFilename()) ?? '';    
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getPath()
     */
    public function getPath() : string
    {
        return $this->pathFromConstructor;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getSize()
     */
    public function getSize() : ?int
    {
        if (null !== $attr = $this->getFileBehavior()->getFileSizeAttribute()) {
            return $this->getFileDataColumn($attr)->getValue(0);
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
        if (null !== $dateTime = $this->getModifiedOn()) {
            return $dateTime->getTimestamp();
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
        if (null !== $dateTime = $this->getCreatedOn()) {
            return $dateTime->getTimestamp();
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
    public function openFile(string $mode = null) : FileInterface
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
        if (null !== $attr = $this->getFileBehavior()->getTimeModifiedAttribute()) {
            return DateTimeDataType::castToPhpDate($this->getFileDataColumn($attr)->getValue(0));
        }
        return null;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Filesystem\FileInfoInterface::getCreatedOn()
     */
    public function getCreatedOn(): ?\DateTimeInterface
    {
        if (null !== $attr = $this->getFileBehavior()->getTimeCreatedAttribute()) {
            return DateTimeDataType::castToPhpDate($this->getFileDataColumn($attr)->getValue(0));
        }
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
        $path = $this->getPath();
        if (null !== ($filename = $this->getFilename(true)) && ! StringDataType::endsWith($path, $filename)) {
            if ($this->hasFilenameMask() === true) {
                $path = StringDataType::substringBefore($path, self::SLASH . $this->getFilenameMask(), $path, false, true);
            }
            $path .= self::SLASH . $filename;
        }
        return $path;
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
        $val = null;
        if (null !== $attr = $this->getFileBehavior()->getMimeTypeAttribute()) {
            $val = $this->getFileDataColumn($attr)->getValue(0);
        }
        return $val;
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
        // TODO re-read data here?
        return $this->getFileDataSheet()->countRows() === 1;
    }
}