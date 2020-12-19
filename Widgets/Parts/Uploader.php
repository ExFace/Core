<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\Widgets\Button;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Actions\CreateData;
use exface\Core\Interfaces\Actions\ActionInterface;


/**
 * Controls how files are uploaded in widgets, that support uploads.
 * 
 * There are two main operating modes: 
 * 
 * - instant upload with a separate action call every time one or more files are selected
 * - deferred upload by an externally defined action, where the uploader widget simply
 * action as an input widget providing file contents and attributes as input data.
 * 
 * The two modes are controlled by the `instant_upload` property. If `instant_upload` is
 * enabled, the `instant_upload_action` can be used to customize the upload action. 
 * 
 * In any case, restrictions can be applied, regulating how many and what type of files 
 * can be uploaded:
 * 
 * - `max_files`
 * - `allowed_mime_types`
 * - `allowed_file_extensions`
 * - `max_filename_length`
 * - `max_file_size_mb`
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
    
    private $maxFiles = null;
    
    private $uploadButton = null;
    
    private $uploadActionUxon = null;
    
    private $instantUpload = true;
    
    /**
     * 
     * @param WidgetInterface $widget
     * @param UxonObject $uxon
     * @param bool $instantUpload
     */
    public function __construct(WidgetInterface $widget, UxonObject $uxon = null, bool $instantUpload = true)
    {
        $this->widget = $widget;
        $this->instantUpload = $instantUpload;
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
    
    /**
     *
     * @return int|null
     */
    public function getMaxFiles() : ?int
    {
        return $this->maxFiles;
    }
    
    /**
     * Maximum number of files to upload - unlimited by default.
     * 
     * @uxon-property max_files
     * @uxon-type integers
     * 
     * @param int $value
     * @return Uploader
     */
    public function setMaxFiles(int $value) : Uploader
    {
        $this->maxFiles = $value;
        return $this;
    }
    
    /**
     * 
     * @return Button
     */
    public function getInstantUploadButton() : Button
    {
        if ($this->uploadButton === null) {
            if ($this->getWidget() instanceof iHaveButtons) {
                $btnType = $this->getWidget()->getButtonWidgetType();
            } else {
                $btnType = 'Button';
            }
            $this->uploadButton = WidgetFactory::createFromUxonInParent($this->getWidget(), $this->getInstantUploadActionUxon(), $btnType);
        }
        return $this->uploadButton;
    }
    
    /**
     * Use a custom action configuration for uploading
     *
     * @uxon-property instant_upload_action
     * @uxon-type \exface\Core\CommonLogic\AbstractAction
     * @uxon-template {"alias": ""}
     * @uxon-default {"alias": "exface.Core.CreateData"}
     * 
     * @param UxonObject $uxon
     * @return Uploader
     */
    public function setInstantUploadAction(UxonObject $uxon) : Uploader
    {
        $this->uploadActionUxon = $uxon;
        return $this;
    }
    
    /**
     * 
     * @return UxonObject
     */
    protected function getInstantUploadActionUxon() : UxonObject
    {
        if ($this->uploadActionUxon === null) {
            return new UxonObject([
                'action_alias' => CreateData::class
            ]);
        }
        return $this->uploadActionUxon;
    }
    
    /**
     * 
     * @return ActionInterface
     */
    public function getInstantUploadAction() : ActionInterface
    {
        return $this->getInstantUploadButton()->getAction();
    }
    
    /**
     * 
     * @return bool
     */
    public function isInstantUpload() : bool
    {
        return $this->instantUpload;
    }
    
    /**
     * Upload files via `instant_upload_action` (TRUE) or use them as input data for external actions (FALSE).
     * 
     * The default setting depends on the widget, that uses the uploader: a regular `FileUploader`
     * will act as an input widget by default, simply passing the files binary data to it's
     * action as input, while a `FileList` will instantly save files on the server because
     * its main object is the file itself.
     * 
     * @uxon-property instant_upload
     * @uxon-type boolean
     * 
     * @param bool $value
     * @return Uploader
     */
    public function setInstantUpload(bool $value) : Uploader
    {
        $this->instantUpload = $value;
        return $this;
    }
}