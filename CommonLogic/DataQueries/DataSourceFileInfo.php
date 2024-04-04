<?php
namespace exface\Core\CommonLogic\DataQueries;

use exface\Core\Behaviors\FileBehavior;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\NotImplementedError;
use exface\Core\DataTypes\BinaryDataType;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\OverflowException;
use exface\Core\Interfaces\Model\Behaviors\FileBehaviorInterface;

/**
 * Custom splFileInfo implementation working with files stored in data sources if the meta object has the `FileBehavior`.
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
class DataSourceFileInfo extends \SplFileInfo
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
     * @return string
     */
    public function getFolder() : string
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
    public function getContents()
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
     * @see \SplFileInfo::getPath()
     */
    public function getPath() 
    {
        return FilePathDataType::findFolderPath($this->pathname);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::getFilename()
     */
    public function getFilename() 
    {
        if ($this->filename === null) {
            if (null !== $attr = $this->getFileBehavior()->getFilenameAttribute()) {
                $this->filename = $this->getFileDataSheet()->getColumns()->getByAttribute($attr)->getValue(0);
            } elseif ($this->hasFilenameMask()) {
                $this->filename = $this->getFilenameMask();
            }
        }
        return $this->filename ?? '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::getExtension()
     */
    public function getExtension() 
    {
        return FilePathDataType::findExtension($this->getFilename());    
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::getBasename()
     */
    public function getBasename(string $suffix = null) 
    {
        $filename = $this->getFilename();
        if ($suffix !== null) {
            return StringDataType::substringBefore($filename, $suffix, $filename, false, true);
        }
        return $filename;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::getPathname()
     */
    public function getPathname() 
    {
        return $this->pathname;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::getPerms()
     */
    public function getPerms() 
    {
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::getInode()
     */
    public function getInode() 
    {
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::getSize()
     */
    public function getSize() 
    {
        if (null !== $attr = $this->getFileBehavior()->getFileSizeAttribute()) {
            return $this->getFileDataSheet()->getColumns()->getByAttribute($attr)->getValue(0);
        }
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::getOwner()
     */
    public function getOwner() 
    {
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::getGroup()
     */
    public function getGroup() 
    {
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::getATime()
     */
    public function getATime() 
    {
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::getMTime()
     */
    public function getMTime() 
    {
        if (null !== $attr = $this->getFileBehavior()->getTimeModifiedAttribute()) {
            return $this->getFileDataSheet()->getColumns()->getByAttribute($attr)->getValue(0);
        }
        return false;
        
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::getCTime()
     */
    public function getCTime() 
    {
        if (null !== $attr = $this->getFileBehavior()->getTimeCreatedAttribute()) {
            return $this->getFileDataSheet()->getColumns()->getByAttribute($attr)->getValue(0);
        }
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::getType()
     */
    public function getType() 
    {
        return 'file';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::isWritable()
     */
    public function isWritable() 
    {
        return $this->getMetaObject()->isWritable();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::isReadable()
     */
    public function isReadable() 
    {
        return $this->getMetaObject()->isReadable();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::isExecutable()
     */
    public function isExecutable() 
    {
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::isFile()
     */
    public function isFile() 
    {
        return true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::isDir()
     */
    public function isDir() 
    {
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::isLink()
     */
    public function isLink() 
    {
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::getLinkTarget()
     */
    public function getLinkTarget() 
    {
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::getRealPath()
     */
    public function getRealPath() 
    {
        return $this->getPath() . self::SLASH . $this->getFilename();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::getFileInfo()
     */
    public function getFileInfo (string $class_name = null) 
    {
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::getPathInfo()
     */
    public function getPathInfo(string $class_name = null) 
    {
        throw new NotImplementedError('Method DataSourceFileInfo::getPathInfo() not implemented yet!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::openFile()
     */
    public function openFile(string $open_mode = null, bool $use_include_path = null, $context = null) 
    {
        throw new NotImplementedError('Method DataSourceFileInfo::getPathInfo() not implemented yet!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::setFileClass()
     */
    public function setFileClass(string $class_name = null) 
    {
        $this->fileClass = $class_name;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SplFileInfo::setInfoClass()
     */
    public function setInfoClass(string $class_name = null) 
    {
        $this->infoClass = $class_name;
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
}