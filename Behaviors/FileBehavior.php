<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\Behaviors\FileBehaviorInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;

/**
 * Makes the object behave as a file regardless of its data source (e.g. files in DB, etc.)
 * 
 * Many features like the `DataSourceFileConnector` required objects, that represent files to have
 * this behavior. Others may work even without the behavior, but will require much more configuration:
 * e.g. widgets `ImageGallery`, `FileList`, etc.
 * 
 * For use in external libaries, there is an adapter for the `splFileinfo` class, that allows PHP code
 * to use items from an object with `FileBehavior` as files: `DataSourceFileInfo`.
 * 
 * @author Andrej Kabachnik
 *
 */
class FileBehavior extends AbstractBehavior implements FileBehaviorInterface
{    
    private $filenameAttributeAlias = null;
    
    private $folderAttributeAlias = null;
    
    private $contentsAttributeAlias = null;
    
    private $mimeTypeAttributeAlias = null;
    
    private $fileSizeAttributeAlias = null;
    
    private $timeCreatedAttributeAlias = null;
    
    private $timeModifiedAttributeAlias = null;
    
    private $allowedFileExtensions = [];
    
    private $allowedMimeTypes = [];
    
    private $maxFilenameLength = 255;
    
    private $maxFileSizeMb = null;

    private $imageResizeToMaxSide = null;

    private $imageResizeQuality = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\Behaviors\FileBehaviorInterface::getFilenameAttribute()
     */
    public function getFilenameAttribute() : MetaAttributeInterface
    {
        return $this->getObject()->getAttribute($this->filenameAttributeAlias);
    }
    
    /**
     * Alias of the attribute, that represents the full filename (incl. extension)
     * 
     * @uxon-property filename_attribute
     * @uxon-type metamodel:attribute
     * @uxon-required true
     * 
     * @param string $value
     * @return FileBehavior
     */
    protected function setFilenameAttribute(string $value) : FileBehavior
    {
        $this->filenameAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\Behaviors\FileBehaviorInterface::getContentsAttribute()
     */
    public function getContentsAttribute() : MetaAttributeInterface
    {
        return $this->getObject()->getAttribute($this->contentsAttributeAlias);
    }
    
    /**
     * Alias of the attribute, that represents the file contents
     * 
     * @uxon-property contents_attribute
     * @uxon-type metamodel:attribute
     * @uxon-required true
     * 
     * @param string $value
     * @return FileBehavior
     */
    protected function setContentsAttribute(string $value) : FileBehavior
    {
        $this->contentsAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\Behaviors\FileBehaviorInterface::getMimeTypeAttribute()
     */
    public function getMimeTypeAttribute() : ?MetaAttributeInterface
    {
        return $this->mimeTypeAttributeAlias === null ? null : $this->getObject()->getAttribute($this->mimeTypeAttributeAlias);
    }
    
    /**
     * Alias of the attribute, that represents the mime type of the file
     *
     * @uxon-property mime_type_attribute
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return FileBehavior
     */
    protected function setMimeTypeAttribute(string $value) : FileBehavior
    {
        $this->mimeTypeAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\Behaviors\FileBehaviorInterface::getFileSizeAttribute()
     */
    public function getFileSizeAttribute() : ?MetaAttributeInterface
    {
        return $this->fileSizeAttributeAlias === null ? null : $this->getObject()->getAttribute($this->fileSizeAttributeAlias);
    }
    
    /**
     * Alias of the attribute, that contains the size of the file in bytes (optional)
     *
     * @uxon-property file_size_attribute
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return FileBehavior
     */
    protected function setFileSizeAttribute(string $value) : FileBehavior
    {
        $this->fileSizeAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\Behaviors\FileBehaviorInterface::getTimeCreatedAttribute()
     */
    public function getTimeCreatedAttribute() : ?MetaAttributeInterface
    {
        return $this->timeCreatedAttributeAlias === null ? null : $this->getObject()->getAttribute($this->timeCreatedAttributeAlias);
    }
    
    /**
     * Alias of the attribute, that contains the creation time of the file (optional)
     *
     * @uxon-property time_created_attribute
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return FileBehavior
     */
    protected function setTimeCreatedAttribute(string $value) : FileBehavior
    {
        $this->timeCreatedAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\Behaviors\FileBehaviorInterface::getTimeModifiedAttribute()
     */
    public function getTimeModifiedAttribute() : ?MetaAttributeInterface
    {
        return $this->timeModifiedAttributeAlias === null ? null : $this->getObject()->getAttribute($this->timeModifiedAttributeAlias);
    }
    
    /**
     * Alias of the attribute, that contains the modification time of the file (optional)
     *
     * @uxon-property time_modified_attribute
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return FileBehavior
     */
    protected function setTimeModifiedAttribute(string $value) : FileBehavior
    {
        $this->timeModifiedAttributeAlias = $value;
        return $this;
    }
    
    /**
     *
     * @return string[]
     */
    public function getAllowedFileExtensions() : array
    {
        return $this->allowedFileExtensions;
    }
    
    /**
     * Uploadable file types/extensions
     *
     * @uxon-property allowed_file_extensions
     * @uxon-type array
     * @uxon-template ["pdf"]
     *
     * @param array|UxonObject $value
     * @return FileBehaviorInterface
     */
    public function setAllowedFileExtensions($value) : FileBehaviorInterface
    {
        if ($value instanceof UxonObject) {
            $value = $value->toArray();
        }
        
        if (! is_array($value)) {
            throw new BehaviorConfigurationError($this, 'Invalid value "' . $value . '" for property `allowed_file_extensions` for object ' . $this->getObject()->__toString() . '!');
        }
        
        $this->allowedFileExtensions = $value;
        return $this;
    }
    
    /**
     *
     * @return string[]
     */
    public function getAllowedMimeTypes() : array
    {
        return $this->allowedMimeTypes;
    }
    
    /**
     * Uploadable file types/extensions
     *
     * @uxon-property allowed_mime_types
     * @uxon-type array
     * @uxon-template ["application/pdf"]
     *
     * @param array|UxonObject $value
     * @return FileBehaviorInterface
     */
    public function setAllowedMimeTypes($value) : FileBehaviorInterface
    {
        if ($value instanceof UxonObject) {
            $value = $value->toArray();
        }
        
        if (! is_array($value)) {
            throw new BehaviorConfigurationError($this, 'Invalid value "' . $value . '" for property `allowed_mime_types` of FileBehavior for object ' . $this->getObject()->__toString() . '!');
        }
        
        $this->allowedMimeTypes = $value;
        return $this;
    }
    
    /**
     *
     * @return int
     */
    public function getMaxFilenameLength() : int
    {
        return $this->maxFilenameLength;
    }
    
    /**
     * Maximum length of the filename (including the extension)
     *
     * @uxon-property max_filename_length
     * @uxon-type integer
     * @uxon-default 255
     *
     * @param int $value
     * @return FileBehaviorInterface
     */
    public function setMaxFilenameLength(int $value) : FileBehaviorInterface
    {
        $this->maxFilenameLength = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\Behaviors\FileBehaviorInterface::getMaxFileSizeInMb()
     */
    public function getMaxFileSizeInMb() : ?float
    {
        return $this->maxFileSizeMb;
    }
    
    /**
     * Maximum allowed file size in MB
     * 
     * @uxon-property max_file_size_in_mb
     * @uxon-type number
     * 
     * @param float $value
     * @return FileBehavior
     */
    protected function setMaxFileSizeInMb(float $value) : FileBehavior
    {
        $this->maxFileSizeMb = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\Behaviors\FileBehaviorInterface::getFolderAttribute()
     */
    public function getFolderAttribute() : ?MetaAttributeInterface
    {
        if ($this->folderAttributeAlias === null) {
            // There was a bit of a conflict here... Since the DataSourceFileInfo expects
            // the UID to be sort of a folder, it seems to be important to set this up
            // explicitly for any file, that is stored in the database or similar. If
            // it was not done by the app designer, it caused trouble.
            // On the other hand, using the UID as the older is simply wrong for files
            // stored in real file systems. So how to detect this properly? The current
            // solution uses an explicitly provided folder attribute always and if that
            // is not provided AND the UID is not used in any other file attribute, than
            // it is assumed to be the UID.
            // TODO perhaps, we have missed something. Actually, it should not be a big
            // problem to have files without folders. But there was no time to dig into
            // all the exceptions happening in this case.
            if ($this->getObject()->hasUidAttribute()) {
                $uidAttr = $this->getObject()->getUidAttribute();
                $uidAlias = $uidAttr->getAliasWithRelationPath();
                $otherAliases = [
                    $this->folderAttributeAlias,
                    $this->contentsAttributeAlias,
                    $this->filenameAttributeAlias,
                    $this->mimeTypeAttributeAlias,
                    $this->timeCreatedAttributeAlias,
                    $this->timeModifiedAttributeAlias
                ];
                if (! in_array($uidAlias, $otherAliases)){
                    $this->folderAttributeAlias = $uidAlias;
                }
                return $uidAttr;
            }
        }
        return $this->folderAttributeAlias === null ? null : $this->getObject()->getAttribute($this->folderAttributeAlias);
    }
    
    /**
     * Alias of the attribute, that contains the folder path of the file
     * 
     * @uxon-property folder_attribute
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return FileBehavior
     */
    protected function setFolderAttribute(string $value) : FileBehavior
    {
        $this->folderAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\Behaviors\FileBehaviorInterface::getFileAttributes()
     */
    public function getFileAttributes() : array
    {
        $attrs = [];
        if (null !== $attr = $this->getFilenameAttribute()) {
            $attrs[] = $attr;
        }
        if (null !== $attr = $this->getFolderAttribute()) {
            $attrs[] = $attr;
        }
        if (null !== $attr = $this->getContentsAttribute()) {
            $attrs[] = $attr;
        }
        if (null !== $attr = $this->getFileSizeAttribute()) {
            $attrs[] = $attr;
        }
        if (null !== $attr = $this->getMimeTypeAttribute()) {
            $attrs[] = $attr;
        }
        if (null !== $attr = $this->getTimeCreatedAttribute()) {
            $attrs[] = $attr;
        }
        if (null !== $attr = $this->getTimeModifiedAttribute()) {
            $attrs[] = $attr;
        }
        return $attrs;
    }

    /**
     * 
     * @return int|null
     */
    public function getImageResizeToMaxSide() : ?int
    {
        return $this->imageResizeToMaxSide;
    }

    /**
     * Auto-resize uploaded images to the specified maximum of pixels for the longer side of the image.
     * 
     * If set, the uploader will resize large images, so that their longest side matches
     * the given amount of pixels while preserving the aspect ratio.
     * 
     * @uxon-property image_resize_to_max_side
     * @uxon-type int
     * 
     * @param int $pixels
     * @return FileBehaviorInterface
     */
    protected function setImageResizeToMaxSide(int $pixels) : FileBehaviorInterface
    {
        $this->imageResizeToMaxSide = $pixels;
        return $this;
    }

    /**
     * 
     * @return int
     */
    public function getImageResizeQuality(int $default = 92) : int
    {
        return $this->imageResizeQuality ?? $default;
    }

    /**
     * Controls the quality/size of resized images
     * 
     * A Number between 0 and 100 indicating the image quality to be used when resizing 
     * images with file formats that support lossy compression (such as image/jpeg or 
     * image/webp). 
     * 
     * Smaller number lead to lower quality and smaller files while higher values
     * produce better quality and larger files.
     * 
     * @uxon-property image_resize_quality
     * @uxon-type int
     * @uxon-default 92
     * 
     * @param float $betweenZeroAndOne
     * @return FileBehaviorInterface
     */
    protected function setImageResizeQuality(int $percent) : FileBehaviorInterface
    {
        if ($percent < 0 || $percent > 100) {
            throw new BehaviorConfigurationError($this, 'Invalid image resize quality setting "' . $percent . '" for FileBehavior: expecting number between 0 and 100');
        }
        $this->imageResizeQuality = $percent;
        return $this;
    }
}