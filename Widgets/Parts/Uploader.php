<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\DataTypes\NumberDataType;


/**
 * The value of a conditional widget property is defined by one or more conditions.
 * 
 * @author Andrej Kabachnik
 * 
 */
class Uploader implements WidgetPartInterface
{
    use ImportUxonObjectTrait;
    
    private $widget = null;
    
    private $allowedFileExtensions = [];
    
    private $allowedMimeTypes = [];
    
    private $maxFilenameLength = 255;
    
    private $maxFileSizeMb = 10;
    
    public function __construct(WidgetInterface $widget, UxonObject $uxon = null)
    {
        $this->widget = $widget;
        if ($uxon !== null) {
            $this->importUxonObject($uxon);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject([
            
        ]);
        
        return $uxon;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPartInterface::getWidget()
     */
    public function getWidget() : WidgetInterface
    {
        return $this->widget;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->widget->getWorkbench();
    }
    
    /**
     *
     * @return array
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
     * @return Uploader
     */
    public function setAllowedFileExtensions($value) : Uploader
    {
        if ($value instanceof UxonObject) {
            $value = $value->toArray();
        }
        
        if (! is_array($value)) {
            throw new WidgetConfigurationError($this->getWidget(), 'Invalid value "' . $value . '" for property allowed_file_extensions of widget "' . $this->getWidget()->getWidgetType() . '"!');
        }
        
        $this->allowedFileExtensions = $value;
        return $this;
    }
    
    /**
     *
     * @return array
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
     * @return Uploader
     */
    public function setAllowedMimeTypes($value) : Uploader
    {
        if ($value instanceof UxonObject) {
            $value = $value->toArray();
        }
        
        if (! is_array($value)) {
            throw new WidgetConfigurationError($this->getWidget(), 'Invalid value "' . $value . '" for property allowed_mime_types of widget "' . $this->getWidget()->getWidgetType() . '"!');
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
     * @return Uploader
     */
    public function setMaxFilenameLength(int $value) : Uploader
    {
        $this->maxFilenameLength = $value;
        return $this;
    }
    
    /**
     *
     * @return int
     */
    public function getMaxFileSizeMb() : float
    {
        return $this->maxFileSizeMb;
    }
    
    /**
     * Maximum size of uploaded files in megabytes
     * 
     * @uxon-property max_file_size_mb
     * @uxon-type number
     * @uxon-default 255
     * 
     * @param float $value
     * @return Uploader
     */
    public function setMaxFileSizeMb($value) : Uploader
    {
        $this->maxFileSizeMb = NumberDataType::cast($value);
        return $this;
    }
}