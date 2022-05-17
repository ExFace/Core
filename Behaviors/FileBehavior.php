<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\MetaAttributeInterface;

/**
 * Makes the object behave as a file regardless of its data source (e.g. files in DB, etc.)
 * 
 * Widgets, that handle files like `ImageGallery`, `FileList`, etc. will require much less
 * configuration if their object has the `FileBehavior`.
 * 
 * @author Andrej Kabachnik
 *
 */
class FileBehavior extends AbstractBehavior
{    
    private $filenameAttributeAlias = null;
    
    private $contentsAttributeAlias = null;
    
    private $mimeTypeAttributeAlias = null;
    
    private $maxFileSizeMb = null;
    
    /**
     * 
     * @return MetaAttributeInterface
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
     * @return MetaAttributeInterface
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
     * @return MetaAttributeInterface|NULL
     */
    public function getMimeTypeAttribute() : ?MetaAttributeInterface
    {
        return $this->getObject()->getAttribute($this->mimeTypeAttributeAlias);
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
     * @return float|NULL
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
}