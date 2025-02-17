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
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\Behaviors\FileBehaviorInterface;
use exface\Core\Widgets\Popup;
use exface\Core\Interfaces\Widgets\iHaveColumns;
use exface\Core\Behaviors\FileAttachmentBehavior;


/**
 * Controls how files are uploaded in widgets, that support uploads.
 * 
 * The Uploader puts file data in a data sheet for its meta object. Which file properties
 * go to which attributes can be either specified explicitly or determined from the `FileBehavior`
 * automatically if the object has this behavior:
 * 
 * - `filename_attribute` - required
 * - `file_content_attribute` - required
 * - `file_mime_type_attribute`
 * - `file_size_attribute`
 * - `file_modification_time_attribute`
 * 
 * Keep in mind, that different file storage types will require different attributes to be set
 * explicitly when uploading: while a file system only requires the file itself and can determine
 * size, mime type, etc. automatically, database-style storages will store all these attributes
 * separately. The uploader will send data for those attributes, that are either writable or
 * included in the uploader configuration explicitly.
 * 
 * ## Instant upload vs. inclusion in form data
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
 * The uploader can be automatically configured if its object has the `FileBehavior`.
 * If not, you will need to set `filename_attribute_alias`, `file_content_attribute_alias`,
 * etc.
 * 
 * ## Validations and restrictions
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
 * ## Saving additional information with every file
 * 
 * In data widgets like `FileList` or `ImageGallery` with `instant_upload` set to `false`, 
 * additional information can be collected for every file - typical examples are comments, 
 * descriptions, categories, etc. The widget will offer an editor popup for pending uploads
 * to add this information. This popup can be customized via `upload_edit_popup`. By default
 * it will include the filename along with all other editable attributes from the columns
 * of the data widget.
 * 
 * If the uploader is used for an object with `FileAttachmentBehavior`, the `comments_attribute`
 * will be determined automatically if defined in the behavior.
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
    
    private $maxFilenameLength = null;
    
    private $maxFileSizeMb = null;
    
    private $maxFiles = null;
    
    private $uploadButton = null;
    
    private $uploadActionUxon = null;
    
    private $instantUpload = true;
    
    private $filenameAttributeAlias = null;
    
    private $fileContentAttributeAlias = null;
    
    private $fileModificationTimeAttributeAlias = null;
    
    private $mimeTypeAttributeAlias = null;
    
    private $fileSizeAttributeAlias = null;
    
    private $commentsAttributeAlias = null;
    
    private $uxon = null;
    
    private $checkedBehaviorForObject;
    
    private $uploadEditPopupUxon = null;
    
    private $uploadEditPopup = null;
    
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
            $this->uxon = $uxon;
        }
        $this->guessAttributes();
    }
    
    public function getMetaObject() : MetaObjectInterface
    {
        return $this->getWidget()->getMetaObject();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = $this->uxon ?? new UxonObject();
        
        if (! empty($this->allowedFileExtensions)) {
            $uxon->setProperty('allowed_file_extensions', $this->allowedFileExtensions);
        }
        
        if (! empty($this->allowedMimeTypes)) {
            $uxon->setProperty('allowed_mime_types', $this->allowedMimeTypes);
        }
        
        // TODO add other properties
        
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
        return $this->maxFilenameLength ?? 255;
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
    public function getMaxFileSizeMb() : ?float
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
    
    /**
     * 
     * @return string|NULL
     */
    protected function getFileMimeTypeAttributeAlias() : ?string
    {
        return $this->mimeTypeAttributeAlias;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasFileMimeTypeAttribute() : bool
    {
        return $this->mimeTypeAttributeAlias !== null;
    }
    
    /**
     * 
     * @return MetaAttributeInterface
     */
    public function getFileMimeTypeAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->getFileMimeTypeAttributeAlias());
    }
    
    /**
     * The alias of the attribute to save the content type (mime type) to
     *
     * If the uploader is based on an object with `FileBehavior`, the `file_size_attribute_alias`
     * can be determined automatically.
     *
     * @uxon-property file_mime_type_attribute
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return Uploader
     */
    public function setFileMimeTypeAttribute(string $value) : Uploader
    {
        $this->mimeTypeAttributeAlias = $value;
        return $this;
    }
    
    /**
     *
     * @return string|NULL
     */
    protected function getFileSizeAttributeAlias() : ?string
    {
        return $this->fileSizeAttributeAlias;
    }
    
    /**
     *
     * @return bool
     */
    public function hasFileSizeAttribute() : bool
    {
        return $this->fileSizeAttributeAlias !== null;
    }
    
    /**
     *
     * @return MetaAttributeInterface
     */
    public function getFileSizeAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->getFileSizeAttributeAlias());
    }
    
    /**
     * The alias of the attribute to save the file size to
     * 
     * If the uploader is based on an object with `FileBehavior`, the `file_size_attribute_alias`
     * can be determined automatically.
     * 
     * @uxon-property file_size_attribute
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return Uploader
     */
    public function setFileSizeAttribute(string $value) : Uploader
    {
        $this->fileSizeAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getFilenameAttributeAlias() : ?string
    {
        return $this->filenameAttributeAlias;
    }
    
    /**
     * 
     * @return MetaAttributeInterface
     */
    public function getFilenameAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->getFilenameAttributeAlias());
    }
    
    /**
     * The alias of the attribute to save the filename to
     *
     * If the uploader is based on an object with `FileBehavior`, the `filename_attribute_alias`
     * can be determined automatically.
     * 
     * @uxon-property filename_attribute
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return Uploader
     */
    public function setFilenameAttribute(string $value) : Uploader
    {
        $this->filenameAttributeAlias = $value;
        return $this;
    }

    /**
     * 
     * @return string|NULL
     */
    protected function getFileContentAttributeAlias() : ?string
    {
        return $this->fileContentAttributeAlias;
    }
    
    /**
     * 
     * @return MetaAttributeInterface
     */
    public function getFileContentAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->getFileContentAttributeAlias());
    }
    
    /**
     * The alias of the attribute to save the (binary) content to
     *
     * If the uploader is based on an object with `FileBehavior`, the `file_content_attribute_alias`
     * can be determined automatically.
     * 
     * @uxon-property file_content_attribute
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return Uploader
     */
    public function setFileContentAttribute(string $value) : Uploader
    {
        $this->fileContentAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasFileModificationTimeAttribute() : bool
    {
        return $this->fileModificationTimeAttributeAlias !== null;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getFileModificationTimeAttributeAlias() : ?string
    {
        return $this->fileModificationTimeAttributeAlias;
    }
    
    /**
     * 
     * @return MetaAttributeInterface
     */
    public function getFileModificationTimeAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->getFileModificationTimeAttributeAlias());
    }
    
    /**
     * The alias of the attribute to save the last modification date and time of the uploaded file
     *
     * @uxon-property file_modification_time_attribute
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return Uploader
     */
    public function setFileModificationTimeAttribute(string $value) : Uploader
    {
        $this->fileModificationTimeAttributeAlias = $value;
        return $this;
    }
    
    /**
     *
     * @return string|NULL
     */
    protected function getCommentsAttributeAlias() : ?string
    {
        return $this->commentsAttributeAlias;
    }
    
    /**
     *
     * @return bool
     */
    public function hasCommentsAttribute() : bool
    {
        return $this->commentsAttributeAlias !== null;
    }
    
    /**
     *
     * @return MetaAttributeInterface
     */
    public function getCommentsAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->getCommentsAttributeAlias());
    }
    
    /**
     * If set, every upload will allow to add a description/comment
     *
     * If the uploader is based on an object with `FileAttachmentBehavior`, the `comments_attribute`
     * can be determined automatically.
     *
     * @uxon-property comments_attribute
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return Uploader
     */
    public function setCommentsAttribute(string $value) : Uploader
    {
        $this->commentsAttributeAlias = $value;
        return $this;
    }
    
    /**
     * @return void
     */
    protected function guessAttributes()
    {
        /* @var $behavior \exface\Core\Behaviors\FileBehavior */
        if ($this->checkedBehaviorForObject !== $this->getMetaObject() && null !== $behavior = $this->getMetaObject()->getBehaviors()->getByPrototypeClass(FileBehaviorInterface::class)->getFirst()) {
            if ($this->fileContentAttributeAlias === null && null !== $attr = $behavior->getContentsAttribute()) {
                $this->setFileContentAttribute($attr->getAliasWithRelationPath());
            }
            if ($this->filenameAttributeAlias === null && null !== $attr = $behavior->getFilenameAttribute()) {
                $this->setFilenameAttribute($attr->getAliasWithRelationPath());
            }
            if ($this->mimeTypeAttributeAlias === null && null !== $attr = $behavior->getMimeTypeAttribute()) {
                if ($attr->isWritable()) {
                    $this->setFileMimeTypeAttribute($attr->getAliasWithRelationPath());
                }
            }
            if ($this->fileSizeAttributeAlias === null && null !== $attr = $behavior->getFileSizeAttribute()) {
                if ($attr->isWritable()) {
                    $this->setFileSizeAttribute($attr->getAliasWithRelationPath());
                }
            }
            if ($this->fileModificationTimeAttributeAlias === null && null !== $attr = $behavior->getMimeTypeAttribute()) {
                if ($attr->isWritable()) {
                    $this->setFileModificationTimeAttribute($attr->getAliasWithRelationPath());
                }
            }
            if ($this->commentsAttributeAlias === null && ($behavior instanceof FileAttachmentBehavior) && null !== $attr = $behavior->getCommentsAttribute()) {
                $this->setCommentsAttribute($attr->getAliasWithRelationPath());
            }
            if ($this->maxFileSizeMb === null && null !== $val = $behavior->getMaxFileSizeInMb()) {
                $this->setMaxFileSizeMb($val);
            }
            if ($this->maxFilenameLength === null && null !== $val = $behavior->getMaxFilenameLength()) {
                $this->setMaxFilenameLength($val);
            }
            if (empty($this->allowedMimeTypes) === true && empty($val = $behavior->getAllowedMimeTypes()) === false) {
                $this->setAllowedMimeTypes($val);
            }
            if (empty($this->allowedFileExtensions) === true && empty($val = $behavior->getAllowedFileExtensions()) === false) {
                $this->setAllowedFileExtensions($val);
            }
        }
        
        $this->checkedBehaviorForObject = $this->getMetaObject();
    }
    
    /**
     * Returns an array of data column names, that are expected to be added to action data by the uploader.
     * 
     * Note: the widget using the uploader does not neccessarily "know" about these columns: for example,
     * an ImageGallery will not need the file contents column or the file size column - these are only
     * added by the uploader.
     * 
     * @see \exface\Core\Widgets\Data::getActionDataColumnNames()
     * 
     * @return string[]
     */
    public function getActionDataColumnNames() : array
    {
        $cols = [];
        $cols[] = \exface\Core\CommonLogic\DataSheets\DataColumn::sanitizeColumnName($this->getFileContentAttribute()->getAliasWithRelationPath());
        $cols[] = \exface\Core\CommonLogic\DataSheets\DataColumn::sanitizeColumnName($this->getFilenameAttribute()->getAliasWithRelationPath());
        if ($this->hasFileModificationTimeAttribute()) {
            $cols[] = \exface\Core\CommonLogic\DataSheets\DataColumn::sanitizeColumnName($this->getFileModificationTimeAttribute()->getAliasWithRelationPath());
        }
        if ($this->hasFileSizeAttribute()) {
            $cols[] = \exface\Core\CommonLogic\DataSheets\DataColumn::sanitizeColumnName($this->getFileSizeAttribute()->getAliasWithRelationPath());
        }
        if ($this->hasFileMimeTypeAttribute()) {
            $cols[] = \exface\Core\CommonLogic\DataSheets\DataColumn::sanitizeColumnName($this->getFileMimeTypeAttribute()->getAliasWithRelationPath());
        }
        if ($this->hasCommentsAttribute()) {
            $cols[] = \exface\Core\CommonLogic\DataSheets\DataColumn::sanitizeColumnName($this->getCommentsAttribute()->getAliasWithRelationPath());
        }
            
        return $cols;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasUploadEditPopup() : bool
    {
        return $this->isInstantUpload() === false;
    }
    
    /**
     * 
     * @return Popup|NULL
     */
    public function getUploadEditPopup() : ?Popup
    {
        if (! $this->hasUploadEditPopup()) {
            return null;
        }
        if ($this->uploadEditPopup === null) {
            $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
            if ($this->uploadEditPopupUxon !== null) {
                $uxon = $this->uploadEditPopupUxon;
            } else {
                $aliasesAdded = [];
                $uxon = new UxonObject([
                    'widgets' => [
                        [
                            'attribute_alias' => $this->getFilenameAttributeAlias(), 
                            'disabled' => false
                        ]
                    ]
                ]);
                $aliasesAdded[] = $this->getFilenameAttributeAlias();
                if ($this->hasCommentsAttribute()) {
                    $uxon->appendToProperty('widgets', new UxonObject([
                        'attribute_alias' => $this->getCommentsAttributeAlias()
                    ]));
                    $aliasesAdded[] = $this->getCommentsAttributeAlias();
                }
                if ($this->getWidget() instanceof iHaveColumns) {
                    foreach ($this->getWidget()->getColumns() as $col) {
                        if ($col->isBoundToAttribute() && $col->getAttribute()->isEditable()) {
                            if (! in_array($col->getAttributeAlias(), $aliasesAdded)) {
                                $uxon->appendToProperty('widgets', new UxonObject(['attribute_alias' => $col->getAttributeAlias()]));
                                $aliasesAdded[] = $col->getAttributeAlias();
                            }
                        }
                    }
                }
            }
            if (! $uxon->hasProperty('caption')) {
                $uxon->setProperty('caption', $translator->translate('WIDGET.UPLOADER.UPLOAD_EDIT_POPUP.TITLE')); 
            }
            $this->uploadEditPopup = WidgetFactory::createFromUxonInParent($this->getWidget(), $uxon, 'Popup');
        }
        return $this->uploadEditPopup;
    }
    
    /**
     * Define a popup to be opened to edit the properties of an uploaded (but not yet saved!) file
     * 
     * @uxon-property upload_edit_popup
     * @uxon-type \exface\Core\Widgets\Popup
     * @uxon-template {"widgets": [{"attribute_alias": ""}]}
     * 
     * @param UxonObject $value
     * @return Uploader
     */
    public function setUploadEditPopup(UxonObject $value) : Uploader
    {
        $this->uploadEditPopupUxon = $value;
        return $this;
    }
}