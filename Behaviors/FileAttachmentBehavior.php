<?php
namespace exface\Core\Behaviors;

use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\Behaviors\FileBehaviorInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\Events\DataSheet\OnCreateDataEvent;
use exface\Core\Events\DataSheet\OnUpdateDataEvent;
use exface\Core\Events\DataSheet\OnDeleteDataEvent;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Events\DataSheetEventInterface;

/**
 * Marks an object as attachment - a link between a document and a file
 * 
 * ## Saving comments for each attachment
 * 
 * Attachments often include additional information beside the file itself.
 * Aparat from the link to the object owning the attachment, a common
 * scenario is saving descriptions or comments for each attachment. 
 * 
 * This can be greatly simplified by setting `comments_attribute` in this behavior. 
 * This will tell all widgets with an `uploader`, that the user should be 
 * able to add a comment to every file being uploaded. How exaclty this is 
 * done, depends on the specific widget and the facade used, but it will
 * certainly result in a consistent way to comment attachments accross the
 * entire app.
 * 
 * ## Deleting files (or not)
 * 
 * Normally, when an attachment is deleted, the attached file is deleted too. 
 * Thechnically the file is deleted after the attachment link. However, since 
 * most file storages do not support transactions, this may theoretically
 * lead to attachments loosing their files - e.g. if the transaction is rolled
 * back after the file was deleted.
 * 
 * If files are really critical, deleting them can be disabled completely by
 * setting `delete_files_when_attachments_deleted` to `false`. This will force
 * files to be kept even if the attachments are deleted.
 * 
 * ## Examples
 * 
 * ### Select folder based on data conditions
 * 
 * ```
 *  {
 *      "file_relation": "file_storage",
 *      "override_file_attributes": {
 *          "filename_attribute": "filename",
 *          "time_created_attribute": "created_on",
 *          "time_modified_attribute": "modified_on"
 *      },
 *      "path_conditions": {
 *          "invoices/[#invoice_id#]": {
 *              "operator": "AND",
 *              "conditions": [
 *                  {"expression": "invoice_id", "comparator": "!==", "value": "NULL"}
 *              ]
 *          },
 *          "orders/[#order_id#]": {
 *              "operator": "AND",
 *              "conditions": [
 *                  {"expression": "order_id", "comparator": "!==", "value": "NULL"}
 *              ]
 *          }
 *      }
 *  }
 *  
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class FileAttachmentBehavior extends AbstractBehavior implements FileBehaviorInterface
{    
    private $fileRelationAlias = null;
    
    private $fileRelationPath = null;
    
    private $fileBehavior = null;
    
    private $filenameAttributeAlias = null;
    
    private $mimeTypeAttributeAlias = null;
    
    private $fileSizeAttributeAlias = null;
    
    private $timeCreatedAttributeAlias = null;
    
    private $timeModifiedAttributeAlias = null;
    
    private $allowedFileExtensions = null;
    
    private $allowedMimeTypes = null;
    
    private $maxFilenameLength = null;
    
    private $maxFileSizeMb = null;
    
    private $commentsAttributeAlias = null;
    
    private $overrideFileAttributes = [];
    
    private $deleteFileWhenAttachmentDeleted = true;
    
    private $pendingSheets = [];
    
    private $inProgress = false;

    private $imageResizeToMaxSide = null;

    private $imageResizeQuality = null;
    
    /**
     * Relation path to the file storage object
     * 
     * @uxon-property file_relation
     * @uxon-type metamodel:relation
     * @uxon-required true
     * 
     * @param string $value
     * @return FileAttachmentBehavior
     */
    protected function setFileRelation(string $value) : FileAttachmentBehavior
    {
        $this->fileBehavior = null;
        $this->fileRelationPath = null;
        $this->fileRelationAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return MetaRelationInterface
     */
    protected function getFileRelation() : MetaRelationInterface
    {
        return $this->getObject()->getRelation($this->fileRelationAlias);
    }
    
    /**
     * 
     * @return MetaRelationPathInterface
     */
    protected function getFileRelationPath() : MetaRelationPathInterface
    {
        if ($this->fileRelationPath === null) {
            $this->fileRelationPath = RelationPathFactory::createFromString($this->getObject(), $this->fileRelationAlias);
        }
        return $this->fileRelationPath;
    }
    
    /**
     * 
     * @return MetaObjectInterface
     */
    protected function getFileObject() : MetaObjectInterface
    {
        return $this->getFileRelation()->getRightObject();
    }
    
    /**
     * 
     * @return FileBehavior
     */
    protected function getFileBehavior() : FileBehavior
    {
        if ($this->fileBehavior === null) {
            foreach ($this->getFileObject()->getBehaviors() as $b) {
                if ($b instanceof FileBehavior) {
                    $this->fileBehavior = $b;
                    break;
                }
            }
            if ($this->fileBehavior === null) {
                throw new BehaviorConfigurationError($this, 'Cannot find FileBehavior for object ' . $this->getFileObject()->__toString() . '!');
            }
        }
        return $this->fileBehavior;
    }
    
    /**
     * Use attributes of this object instead of real file attributes if the latter are missing or difficult to get
     * 
     * @uxon-property override_file_attributes
     * @uxon-type metamodel:attribute[]
     * @uxon-template {"filename_attribute": "", "file_size_attribute": "", "mime_type_attribute": "", "time_created_attribute": "", "time_modified_attribute": ""}
     * 
     * @param UxonObject $value
     * @throws BehaviorConfigurationError
     * @return FileAttachmentBehavior
     */
    protected function setOverrideFileAttributes(UxonObject $value) : FileAttachmentBehavior
    {
        $this->overrideFileAttributes = $value->toArray(CASE_LOWER);
        
        foreach ($this->overrideFileAttributes as $prop => $attrAlias) {
            if ($attrAlias === '' || $attrAlias === null) {
                continue;
            }
            switch ($prop) {
                case 'filename_attribute': $this->filenameAttributeAlias = $attrAlias; break;
                case 'file_size_attribute': $this->fileSizeAttributeAlias = $attrAlias; break;
                case 'mime_type_attribute': $this->mimeTypeAttributeAlias = $attrAlias; break;
                case 'time_created_attribute': $this->timeCreatedAttributeAlias = $attrAlias; break;
                case 'time_modified_attribute': $this->timeModifiedAttributeAlias = $attrAlias; break;
                default:
                    throw new BehaviorConfigurationError($this, 'Cannot override file attribute "' . $prop . '" in FileAttachmentBehavior!');
            }
        }
        
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\Behaviors\FileBehaviorInterface::getFilenameAttribute()
     */
    public function getFilenameAttribute() : MetaAttributeInterface
    {
        if ($this->filenameAttributeAlias !== null) {
            return $this->getObject()->getAttribute($this->filenameAttributeAlias);
        }
        return $this->rebase($this->getFileBehavior()->getFilenameAttribute());
    }
    
    /**
     * Alias of the attribute, that represents the full filename (incl. extension)
     *
     * @param string $value
     * @return FileBehavior
     */
    protected function setFilenameAttribute(string $value) : FileBehaviorInterface
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
        return $this->rebase($this->getFileBehavior()->getContentsAttribute());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\Behaviors\FileBehaviorInterface::getMimeTypeAttribute()
     */
    public function getMimeTypeAttribute() : ?MetaAttributeInterface
    {
        if ($this->mimeTypeAttributeAlias !== null) {
            return $this->getObject()->getAttribute($this->mimeTypeAttributeAlias);
        }
        $attr = $this->getFileBehavior()->getMimeTypeAttribute();
        return $attr === null ? null : $this->rebase($attr);
    }
    
    /**
     * Alias of the attribute, that represents the mime type of the file
     *
     * @param string $value
     * @return FileBehaviorInterface
     */
    protected function setMimeTypeAttribute(string $value) : FileBehaviorInterface
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
        if ($this->fileSizeAttributeAlias !== null) {
            return $this->getObject()->getAttribute($this->fileSizeAttributeAlias);
        }
        $attr = $this->getFileBehavior()->getFileSizeAttribute();
        return $attr === null ? null : $this->rebase($attr);
    }
    
    /**
     * Alias of the attribute, that contains the size of the file in bytes (optional)
     *
     * @param string $value
     * @return FileBehaviorInterface
     */
    protected function setFileSizeAttribute(string $value) : FileBehaviorInterface
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
        if ($this->timeCreatedAttributeAlias !== null) {
            return $this->getObject()->getAttribute($this->timeCreatedAttributeAlias);
        }
        $attr = $this->getFileBehavior()->getTimeCreatedAttribute();
        return $attr === null ? null : $this->rebase($attr);
    }
    
    /**
     * Alias of the attribute, that contains the creation time of the file (optional)
     *
     * @param string $value
     * @return FileBehaviorInterface
     */
    protected function setTimeCreatedAttribute(string $value) : FileBehaviorInterface
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
        if ($this->timeModifiedAttributeAlias !== null) {
            return $this->getObject()->getAttribute($this->timeModifiedAttributeAlias);
        }
        $attr = $this->getFileBehavior()->getTimeModifiedAttribute();
        return $attr === null ? null : $this->rebase($attr);
    }
    
    /**
     * Alias of the attribute, that contains the modification time of the file (optional)
     *
     * @param string $value
     * @return FileBehaviorInterface
     */
    protected function setTimeModifiedAttribute(string $value) : FileBehaviorInterface
    {
        $this->timeModifiedAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return MetaAttributeInterface|null
     */
    public function getCommentsAttribute() : ?MetaAttributeInterface
    {
        return $this->commentsAttributeAlias === null ? null : $this->getObject()->getAttribute($this->commentsAttributeAlias);
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
     * The alias of the attribute to save attachment description or comments
     * 
     * @uxon-property comments_attribute
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return FileAttachmentBehavior
     */
    protected function setCommentsAttribute(string $value) : FileAttachmentBehavior
    {
        $this->commentsAttributeAlias = $value;
        return $this;
    }
    
    /**
     *
     * @return string[]
     */
    public function getAllowedFileExtensions() : array
    {
        if ($this->allowedFileExtensions !== null) {
            return $this->allowedFileExtensions;
        }
        return $this->getFileBehavior()->getAllowedFileExtensions();
    }
    
    /**
     * Uploadable file extensions
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
        if ($this->allowedMimeTypes !== null) {
            return $this->allowedMimeTypes;
        }
        return $this->getFileBehavior()->getAllowedMimeTypes();
    }
    
    /**
     * Uploadable file types (mime types)
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
        if ($this->maxFilenameLength !== null) {
            return $this->maxFilenameLength;
        }
        return $this->getFileBehavior()->getMaxFilenameLength();
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
    public function getMaxFileSizeInMb(): ?float
    {
        if ($this->maxFileSizeMb !== null) {
            return $this->maxFileSizeMb;
        }
        return $this->getFileBehavior()->getMaxFileSizeInMb();
    }
    
    /**
     * Maximum allowed file size in MB
     * 
     * The file size limit set for the attachment will override this setting of the linked
     * file object. If not set for the attachment, the limit of the file object will be
     * applied.
     *
     * @uxon-property max_file_size_in_mb
     * @uxon-type number
     *
     * @param float $value
     * @return FileAttachmentBehavior
     */
    protected function setMaxFileSizeInMb(float $value) : FileAttachmentBehavior
    {
        $this->maxFileSizeMb = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\Behaviors\FileBehaviorInterface::getFolderAttribute()
     */
    public function getFolderAttribute(): ?MetaAttributeInterface
    {
        $folderAttr = $this->getFileBehavior()->getFolderAttribute();
        return $folderAttr === null ? $folderAttr : $this->rebase($folderAttr);
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
     * @param MetaAttributeInterface $fileAttr
     * @return MetaAttributeInterface
     */
    protected function rebase(MetaAttributeInterface $fileAttr) : MetaAttributeInterface
    {
        return $fileAttr->rebase($this->getFileRelationPath());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $mgr = $this->getWorkbench()->eventManager();
        $mgr->addListener(OnBeforeCreateDataEvent::getEventName(), [$this, 'onBeforeDataSave']);
        $mgr->addListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'onBeforeDataSave']);
        $mgr->addListener(OnCreateDataEvent::getEventName(), [$this, 'onDataSave']);
        $mgr->addListener(OnUpdateDataEvent::getEventName(), [$this, 'onDataSave']);
        
        if ($this->getDeleteFilesWhenAttachmentsDeleted() === true) {
            $mgr->addListener(OnBeforeDeleteDataEvent::getEventName(), [$this, 'onBeforeDataDelete']);
            $mgr->addListener(OnDeleteDataEvent::getEventName(), [$this, 'onDataDelete']);
        }
        
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $mgr = $this->getWorkbench()->eventManager();
        $mgr->removeListener(OnBeforeCreateDataEvent::getEventName(), [$this, 'onBeforeDataSave']);
        $mgr->removeListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'onBeforeDataSave']);
        $mgr->removeListener(OnCreateDataEvent::getEventName(), [$this, 'onDataSave']);
        $mgr->removeListener(OnUpdateDataEvent::getEventName(), [$this, 'onDataSave']);
        
        if ($this->getDeleteFilesWhenAttachmentsDeleted() === true) {
            $mgr->removeListener(OnBeforeDeleteDataEvent::getEventName(), [$this, 'onBeforeDataDelete']);
            $mgr->removeListener(OnDeleteDataEvent::getEventName(), [$this, 'onDataDelete']);
        }
        
        return $this;
    }
    
    /**
     * Separate columns of the attachment and the actual file data for any attachments being saved
     * 
     * The attachment data (just the link to the file) must be saved first. After it has been
     * saved, we can actually save the file itself. This is particularly important if the
     * files are saved with folder paths, that contain information about the attachment: e.g.
     * the UID of the object being attached to - which is often the case.
     * 
     * Once the attachment is saved, all its data becomes available and can be used to save
     * the file. This is done in `onDataSave`.
     * 
     * @param DataSheetEventInterface $event
     */
    public function onBeforeDataSave(DataSheetEventInterface $event)
    {
        // Ignore sheets produced by this behavior
        if ($this->inProgress === true) {
            return;
        }
        // Ignore other objects
        if (! $event->getDataSheet()->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
        
        $ds = $event->getDataSheet();
        
        // Ignore sheets, that are already scheduled for processing
        foreach ($this->pendingSheets as $p) {
            if ($p['dataSheet'] === $ds) {
                return;
            }
        }
        
        // Strip any file-related columns, that cannot be saved directly to the attachment
        // object. They will be handled later
        $fileCols = [];
        $fileVals = [];
        $fileObj = $this->getFileObject();
        foreach ($ds->getColumns() as $col) {
            if ($col->isAttribute() && $col->getAttribute()->isRelated() && $col->getAttribute()->getRelationPath()->getEndObject()->isExactly($fileObj)) {
                $fileCols[] = $col;
                $fileVals[] = $col->getValues();
                $ds->getColumns()->remove($col);
            }
        }
        if (! empty($fileCols)) {
            $this->pendingSheets[] = [
                'dataSheet' => $ds,
                'fileCols' => $fileCols,
                'fileVals' => $fileVals
            ];
        }
        return;
    }
    
    /**
     *
     * @param DataSheetEventInterface $event
     */
    public function onBeforeDataDelete(OnBeforeDeleteDataEvent $event)
    {
        // Ignore sheets produced by this behavior
        if ($this->inProgress === true) {
            return;
        }
        // Ignore other objects
        if (! $event->getDataSheet()->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
        
        $ds = $event->getDataSheet();
        
        // Ignore sheets, that are already scheduled for processing
        foreach ($this->pendingSheets as $p) {
            if ($p['dataSheet'] === $ds) {
                return;
            }
        }
        
        $this->inProgress = true;
        
        $fileRel = $this->getFileRelation();
        $leftKeyCol = $ds->getColumns()->getByAttribute($fileRel->getLeftKeyAttribute());
        if (! $leftKeyCol) {
            switch (true) {
                case $ds->hasUidColumn(true):
                    $attachmentSheet = DataSheetFactory::createFromObject($ds->getMetaObject());
                    $attachmentSheet->getColumns()->addFromSystemAttributes();
                    $leftKeyCol = $attachmentSheet->getColumns()->addFromAttribute($fileRel->getLeftKeyAttribute());
                    $attachmentSheet->getFilters()->addConditionFromColumnValues($ds->getUidColumn());
                    $attachmentSheet->dataRead();
                    break;
                case ! $ds->getFilters()->isEmpty(true):
                    $attachmentSheet = DataSheetFactory::createFromObject($ds->getMetaObject());
                    $attachmentSheet->getColumns()->addFromSystemAttributes();
                    $leftKeyCol = $attachmentSheet->getColumns()->addFromAttribute($fileRel->getLeftKeyAttribute());
                    $attachmentSheet->setFilters($ds->getFilters()->copy());
                    $attachmentSheet->dataRead();
                    break;
                default:
                    throw new BehaviorRuntimeError($this, 'Cannot save files to related storage: cannot read relation key "' . $fileRel->getLeftKeyAttribute()->getAliasWithRelationPath() . '"!');
            }
        } else {
            $attachmentSheet = $ds;
        }
        
        $fileSheet = DataSheetFactory::createFromObject($this->getFileObject());
        $fileSheet->getFilters()->addConditionFromValueArray(
            $this->getFileRelation()->getRightKeyAttribute()->getAliasWithRelationPath(),
            $leftKeyCol->getValues(),
            true
        );
        
        $this->inProgress = false;
        
        $this->pendingSheets[] = [
            'dataSheet' => $ds,
            'deleteSheet' => $fileSheet
        ];
        return;
    }
    
    /**
     * Saves the file data once the attachment has been saved
     * 
     * @param DataSheetEventInterface $event
     * @throws BehaviorRuntimeError
     */
    public function onDataSave(DataSheetEventInterface $event)
    {
        // Ignore sheets produced by this behavior
        if ($this->inProgress === true) {
            return;
        }
        // Ignore other objects
        if (! $event->getDataSheet()->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
        
        $ds = $event->getDataSheet();
        
        // See if any file data was previously stripped from this attachment sheet (in `onBeforeDataSave`)
        $pending = null; 
        $pendingKey = null;
        foreach ($this->pendingSheets as $i => $p) {
            if ($p['dataSheet'] === $ds) {
                $pending = $p;
                $pendingKey = $i;
                break;
            }
        }
        // If no file data is waiting - stop here
        if ($pending === null) {
            return;
        }
        
        $this->inProgress = true;
        
        // See if the relation to the file object (= the path to the file its data source)
        // is already included in the attachment data. If not, we will need to read it here
        // explicitly. This actually happens very often because paths often include UIDs,
        // document numbers and other data from the object being attached to.
        $fileRel = $this->getFileRelation();
        $leftKeyCol = $ds->getColumns()->getByAttribute($fileRel->getLeftKeyAttribute());
        if (! $leftKeyCol) {
            // However, we can only read additional data if our attachment data has UIDs on
            // every row!
            if (! $ds->hasUidColumn(true)) {
                throw new BehaviorRuntimeError($this, 'Cannot save files to related storage: cannot read relation key "' . $fileRel->getLeftKeyAttribute()->getAliasWithRelationPath() . '"!');
            }
            $attachmentSheet = DataSheetFactory::createFromObject($ds->getMetaObject());
            $attachmentSheet->getColumns()->addFromSystemAttributes();
            $leftKeyCol = $attachmentSheet->getColumns()->addFromAttribute($fileRel->getLeftKeyAttribute());
            $attachmentSheet->getFilters()->addConditionFromColumnValues($ds->getUidColumn());
            $attachmentSheet->dataRead();
            // IMPORTANT: make sure, the freshly read data has the same row order, as the event
            // data. You never know for sure, how a file storage will sort the results by default!
            try {
                $attachmentSheet->sortLike($ds);
            } catch (\Throwable $e) {
                throw new BehaviorRuntimeError($this, 'Cannot read required file attachment data to save the corresponding files', null, $e);
            }
        } else {
            // IDEA we actually do not need to save ALL the attachment data again here
            // It would be better just to save the file-related data. It would be better
            // to create a new sheet here and only take system columns and the $leftKeyCol
            // here.
            $attachmentSheet = $ds;
        }
        
        foreach ($pending['fileCols'] as $i => $col) {
            $attachmentSheet->getColumns()->add($col);
            $newCol = $attachmentSheet->getColumns()->get($col->getName());
            $newCol->setValues($pending['fileVals'][$i]);
            if ($attachmentSheet !== $ds) {
                $ds->getColumns()->add($newCol);
            }
        }
        
        // Update the attachment object now that we know it has been created previously.
        // This update will contain the file contents and will lead to creation
        // of the file since we explicitly ask to create missing UIDs here.
        $attachmentSheet->dataUpdate(true, $event->getTransaction());
        
        unset($this->pendingSheets[$pendingKey]);
        $this->inProgress = false;
        
        return;
    }
    
    /**
     * 
     * @param OnDeleteDataEvent $event
     */
    public function onDataDelete(OnDeleteDataEvent $event)
    {
        // Ignore sheets produced by this behavior
        if ($this->inProgress === true) {
            return;
        }
        // Ignore other objects
        if (! $event->getDataSheet()->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
        
        $ds = $event->getDataSheet();
        
        $pending = null;
        $pendingKey = null;
        foreach ($this->pendingSheets as $i => $p) {
            if ($p['dataSheet'] === $ds) {
                $pending = $p;
                $pendingKey = $i;
                break;
            }
        }
        if ($pending === null) {
            return;
        }
        
        $this->inProgress = true;
        
        $fileSheet = $pending['deleteSheet'];
        //FIXME skip delete if the datasheet is empty
        //maybe already dont add that sheet to the pending sheets?
        if (! $fileSheet->isUnfiltered()) {
            $fileSheet->dataDelete($event->getTransaction());
        }        
        
        unset($this->pendingSheets[$pendingKey]);
        $this->inProgress = false;
        
        return;
    }
    
    /**
     * 
     * @return bool
     */
    public function getDeleteFilesWhenAttachmentsDeleted() : bool
    {
        return $this->deleteFileWhenAttachmentDeleted;
    }
    
    /**
     * Set to FALSE to keep files after the attachment object is deleted.
     * 
     * By default attached files are deleted from the file storage when the attachment
     * object is deleted. This can be disabled to keep all uploaeded files - even if they
     * are "detached" from their objects. This can be helpful to ensure, that files are
     * never deleted. 
     * 
     * If an orphaned file is uploaded again to the same path, it will be overwritten, so
     * duplicates should not happen.
     * 
     * @uxon-property delete_files_when_attachments_deleted
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return FileAttachmentBehavior
     */
    protected function setDeleteFilesWhenAttachmentsDeleted(bool $value) : FileAttachmentBehavior
    {
        $this->deleteFileWhenAttachmentDeleted = $value;
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\Behaviors\FileBehaviorInterface::getImageResizeToMaxSide()
     */
    public function getImageResizeToMaxSide() : ?int
    {
        return $this->imageResizeToMaxSide ?? $this->getFileBehavior()->getImageResizeToMaxSide();
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\Behaviors\FileBehaviorInterface::getImageResizeQuality()
     */
    public function getImageResizeQuality(int $default = 92) : int
    {
        return $this->imageResizeQuality ?? $this->getFileBehavior()->getImageResizeQuality($default);
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