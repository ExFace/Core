<?php
namespace exface\Core\Interfaces\Model\Behaviors;

use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;

interface FileBehaviorInterface extends BehaviorInterface
{
    /**
     * 
     * @return MetaAttributeInterface
     */
    public function getFilenameAttribute() : MetaAttributeInterface;
    
    /**
     * 
     * @return MetaAttributeInterface
     */
    public function getContentsAttribute() : MetaAttributeInterface;
    
    /**
     * 
     * @return MetaAttributeInterface|NULL
     */
    public function getMimeTypeAttribute() : ?MetaAttributeInterface;
    
    /**
     * 
     * @return MetaAttributeInterface|NULL
     */
    public function getFileSizeAttribute() : ?MetaAttributeInterface;
    
    /**
     *
     * @return MetaAttributeInterface|NULL
     */
    public function getTimeCreatedAttribute() : ?MetaAttributeInterface;
    
    /**
     *
     * @return MetaAttributeInterface|NULL
     */
    public function getTimeModifiedAttribute() : ?MetaAttributeInterface;
    
    /**
     *
     * @return string[]
     */
    public function getAllowedFileExtensions() : array;
    
    /**
     *
     * @return string[]
     */
    public function getAllowedMimeTypes() : array;
    
    /**
     *
     * @return int
     */
    public function getMaxFilenameLength() : int;
    
    /**
     * 
     * @return float|NULL
     */
    public function getMaxFileSizeInMb() : ?float;
    
    /**
     * 
     * @return MetaAttributeInterface|NULL
     */
    public function getFolderAttribute() : ?MetaAttributeInterface;
    
    /**
     * 
     * @return MetaAttributeInterface[]
     */
    public function getFileAttributes() : array;

    /**
     * Returns the maximum size of the longest image side in pixels
     * 
     * @return int|null
     */
    public function getImageResizeToMaxSide() : ?int;

    /**
     * Returns the default quality setting for file resize operations.
     * 
     * A Number between 0 and 100 indicating the image quality to be used when resizing 
     * images with file formats that support lossy compression (such as image/jpeg or 
     * image/webp). 
     * 
     * Smaller number lead to lower quality and smaller files while higher values
     * produce better quality and larger files.
     * 
     * @param int $default
     * @return int
     */
    public function getImageResizeQuality(int $default = 92) : int;
}