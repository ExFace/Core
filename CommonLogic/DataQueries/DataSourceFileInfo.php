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

/**
 * Custom splFileInfo implementation working with files stored in data sources if the meta object has the `FileBehavior`.
 * 
 * The paths have the following scheme: `metamodel://my.app.ObjectAlias/uid_of_file`. 
 * 
 * Currently no folders are supported!
 * 
 * @author Andrej Kabachnik
 *
 */
class DataSourceFileInfo extends \SplFileInfo
{
    const SCHEME = 'metamodel://';
    
    private $pathname = null;
    
    private $folder = null;
    
    private $filename = null;
    
    private $object = null;
    
    private $fileBehavior = null;
    
    private $fileAttributes = [];
    
    private $fileData = null;
    
    private $fileClass = null;
    
    private $infoClass = null;
    
    /**
     * 
     * @param string $uid
     * @param MetaObjectInterface $object
     */
    public function __construct($path, WorkbenchInterface $workbench)
    {
        $matches = [];
        preg_match('@' . self::SCHEME . '([^/]+)/([^/]+)/?(.*)@i', $path, $matches);
        $objectSelector = $matches[1] ?? null;
        $uid = $matches[2] ?? null;
        $filename = $matches[3] ?? null;
        if ($uid === null || $objectSelector === null) {
            throw new UnexpectedValueException('Cannot parse virtual file path "' . $path . '".');
        }
        $this->pathname = $path;
        $this->filename = $filename;
        $this->folder = $uid;
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
            $this->fileData = DataSheetFactory::createFromObject($this->getMetaObject());
            $this->fileData->getFilters()->addConditionFromAttribute($this->getFileBehavior()->getFolderAttribute(), $this->getFolder(), ComparatorDataType::EQUALS);
            $this->fileAttributes = $this->getFileBehavior()->getFileAttributes();
            foreach ($this->fileAttributes as $attr) {
                $this->fileData->getColumns()->addFromAttribute($attr);
            }
            $this->fileData->dataRead();
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
        if (null !== $attr = $this->getFileBehavior()->getFilenameAttribute()) {
            return $this->getFileDataSheet()->getColumns()->getByAttribute($attr)->getValue(0);
        }
        return '';
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
        return $this->pathname;
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