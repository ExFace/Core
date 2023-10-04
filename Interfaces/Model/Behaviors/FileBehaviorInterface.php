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
}