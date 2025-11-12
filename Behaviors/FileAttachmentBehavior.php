<?php
namespace exface\Core\Behaviors;

use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\Model\ExpressionInterface;
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
 * The object of this behavior will appear to the rest of the system, as though it actually contained the referenced file, 
 * even though it will simply hold the file metadata (most importantly the file path). On the other hand, this also means
 * attachments can be easily listed and filtered without actually accessing the file system where the files
 * are actually stored.
 * 
 * This is especially useful if you need to reference large data-items: Instead of being stored
 * as raw values on this object, which could severely slow down your application, they will be saved
 * as files. And whenever you need to access that data, they will automatically be loaded and become available
 * via the attributes of this object. Once everything is configured, you won't have to think about files or
 * folders at all!
 * 
 * ##  Configuration
 * 
 * When using this behavior, you will find yourself having two separate metaobjects for your file attachments:
 * 
 * - the file **storage object** that describes the file system, where the attached files are actually saved - e.g. 
 * a derivative from the local `exface.Core.FILE` object or an object with a cloud storage as data source. The storage
 * object can only save the file contents and, depending on the actual data source, some file attributes like name, 
 * access time, etc., but no metadata like comment, owner user and not even a reference to whatever the file is supposed
 * to be attached to.
 * - the **attachment object** is typically a database table, that contains the link between the file in storage and
 * the object, that it is attached to (e.g. a report, an order, etc.). The attachment object often also includes 
 * additional information like comments, ownership, etc. Technically it needs at least attributes for a relation to the
 * object to attach to and a relation to the storage object. The latter is typically the relative path in the storage.
 * 
 * The behavior is applied to the attachment object and makes it behave as if it was the file itself, so you can
 * use the attachment object in file-related widgets like FileList, ImageGallery, etc.
 * 
 * To make this work, you need to configure the way, how exactly the attachment and storage objects are linked:
 * 
 * - `file_relation`: alias of the attribute, that is the relation from the attachment object to the storage object. 
 * In most cases, this will be an attribute of the attachment object, that holds the relative path to the file in the 
 * storage object - see details below. Enter just the relation/attribute alias here (DO: `file_path`, DON'T: `file_path__...`).
 * - `file_path_calculation`: a formula to calculate the file path if `file_relation` should actually store the path
 * and not live-calculate from some other attributes - see details below.
 * - `override_file_attributes`: Loading file-metadata is slow, which is why it might be faster to have some of it stored
 * directly on this object. You can choose, which attributes of the file-storage object you want to put on this object.
 * The system will use the attributes you provided, instead of the ones on the storage object.
 * For example, if you configure `"mime_type_attribute":"content_type"`, the system will use `your.App.YourObject.content_type`
 * instead of `your.App.YourStorage.MIMETYPE`. It is a good idea in general to override storage attributes, whenever a local
 * equivalent is available.
 * 
 * ### Linking attachment and file object
 * 
 * The `file_relation` must point from each instance of the attachment object to the real file. You can either really
 * store the full path here using `file_path_calculation` or create a read-only relation attribute, that will build
 * the path from other things with a formula or a custom SQL statement (if the attachment object resides in a SQL table).
 * 
 * We recommend to use `file_path_calculation` to persist the file path. This has a number of advantages compared
 * to live calculated paths:
 * 
 * - You can see the file path in the raw attachment data (e.g. the SQL table)
 * - If you choose to change the storage structure, previously saved attachments will retain their paths. You have
 * the choice of migrating them (e.g. via SQL migration) or not.
 * 
 * ## Saving comments for each attachment
 * 
 * Attachments often include additional information beside the file itself,
 * such as comments or descriptions.
 * 
 * This can be greatly simplified by setting `comments_attribute` in this behavior.
 * This will tell all widgets with an `uploader`, that the user should be
 * able to add a comment to every file being uploaded. How exactly this is
 * done, depends on the specific widget and the facade used, but it will
 * certainly result in a consistent way to comment attachments across the
 * entire app.
 * 
 * ## Deleting files (or not)
 * 
 * Normally, when an attachment is deleted, the attached file is deleted too.
 * Technically the file is deleted after the attachment link. However, since
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
 * ### Persisted paths calculated from data
 * 
 * In this example, we are attaching files to an INVOICE object. The FILE_STORAGE object contains the
 * data source pointing to the real file system. The attachment does not care, where exactly the files will
 * be saved, but inside of this storage there will be an `Invoices` folder, where we will create subfolders
 * for every invoice number and store files there. Every filename will get the UID of the attachment object
 * as prefix - this is a good practice to allow users to upload multiple files with the same name without
 * the risc of invisible overwrites.
 * 
 * For example, if uploading `invoice.xlsx` for invoice `I2024-78555` will produce an attachment entry with UID 
 * `12258`, the file will be stored in `Invoices/I2024-78555/12258_invoice.xlsx`. However, the filename prefix
 * will not be visible to users - they will still see `invoice.xlsx` because we explicitly save the FILENAME in
 * the attachment and use `override_file_attributes` to make sure it is treated as the name of the file. Even
 * if we download the file, it will be still named `invoice.xlsx`.
 * 
 * Storing creation/modification timestamps and mime type in the attachment object and overriding the
 * corresponding file attributes allows us to use them as filters/sorters for attachments without querying the
 * slow file system.
 *
 * ```
 *  {
 *      "file_relation": "FILE_STORAGE",
 *      "file_path_calculation": "=Concatenate('Invoices/', INVOICE__NUMBER, '/', UID, '_', FILENAME)"
 *      "override_file_attributes": {
 *          "filename_attribute": "FILENAME",
 *          "time_created_attribute": "CREATED_ON",
 *          "time_modified_attribute": "MODIFIED_ON",
 *          "mime_type_attribute": "MIME_TYPE"
 *      }
 *  }
 * 
 * ```
 * 
 * ### Live-calculated paths
 *
 * Used in `axenox.ETL.webservice_request`, the storage object is `axenox.ETL.webservice_request_storage`. The
 * `body_file` attribute is a read-only SQL statement in this case.
 *
 * ```
 *  {
 *      "file_relation": "body_file",
 *      "override_file_attributes": {
 *          "mime_type_attribute": "content_type",
 *          "time_created_attribute": "CREATED_ON",
 *          "time_modified_attribute": "MODIFIED_ON"
 *      }
 *  }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 * 
 * 
 * ## Access and manipulate file contents in code
 * 
 * While the FileAttachmentBehavior tries to hide all file interactions from a Power-UI perspective,
 * you will have to watch out for some minor gotchas, when interacting with this behavior in code.
 * 
 * 1. If you want to save or load file data via datasheets, use the `file_relation` attribute with
 * relation strings. You don't have to worry about file paths or query builders:
 * 
 * ```
 *  // Loading response data, from a file.
 * 
 *  $responseData = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'axenox.ETL.webservice_response');
 *  $responseData->getColumns()->addFromSystemAttributes();
 *  // To access the file data, we use the file relations.
 *  $responseData->getColumns()->addMultiple([
 *      'body_file__CONTENTS',  // Loading the actual file contents.
 *      'body_file__SIZE'       // Loading the file size.
 *  ]);
 *  $responseData->getColumns()->addFromExpression('webservice_request');
 *  $responseData->getFilters()->addConditionFromString('webservice_request', $requestUid);
 *  $responseData->dataRead();
 * 
 *  // The datasheet now contains both the actual file contents and the file size.
 * 
 * ```
 * 
 * 2. Creating data with `DataSheetInterface::dataUpdate(create_if_uid_not_found true)` is NOT compatible with this behavior!
 * Use `DataSheetInterface::dataCreate()`, instead. Updating data with `dataUpdate()` works fine.
 * 
 * ```
 *  // Create the datasheet.
 *  $dataSheet = DataSheetFactory::createFromObjectIdOrAlias(
 *      $this->facade->getWorkbench(),
 *      'axenox.ETL.webservice_response'
 *  );
 * 
 *  // Adding columns and filters...
 * 
 *  // Since `axenox.ETL.webservice_response` has an active `FileAttachmentBehavior`,
 *  // we can't use `$dataSheet->dataUpdate(true)`.
 *  $dataSheet->dataCreate();
 * 
 *  // We read from the datasheet, because the behavior has now generated important data.
 *  // Most importantly, `body_file` is now calculated, and we need it to be up-to-date.
 *  $dataSheet->dataRead();
 * 
 * ```
 */
class FileAttachmentBehavior extends AbstractBehavior implements FileBehaviorInterface
{
    private $fileRelationAlias = null;
    private $fileRelationPath = null;
    private $fileBehavior = null;
    private string|ExpressionInterface|null $filePathCalcExpression = null;

    private $overrideFileAttributes = [];
    private $filenameAttributeAlias = null;
    private $mimeTypeAttributeAlias = null;
    private $fileSizeAttributeAlias = null;
    private $timeCreatedAttributeAlias = null;
    private $timeModifiedAttributeAlias = null;
    private $commentsAttributeAlias = null;

    private $allowedFileExtensions = null;
    private $allowedMimeTypes = null;
    private $maxFilenameLength = null;
    private $maxFileSizeMb = null;

    private $imageResizeToMaxSide = null;
    private $imageResizeQuality = null;

    private $deleteFileWhenAttachmentDeleted = true;

    private $pendingSheets = [];
    private $inProgress = false;

    /**
     * Relation path to the file storage object.
     *
     * Enter the attribute alias that points to the actual file storage. This attribute should usually
     * have the datatype `File Path`, and be a relation to an object that inherits from `exface.Core.FILE`. Do NOT enter the actual
     * relation, just the attribute alias that contains the relation (DO: `file_path`, DON'T: `file_path__...`).
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
     * Use attributes of this object instead of real file attributes if the latter are missing or difficult to get.
     *
     * Loading file-metadata is slow, which is why it might be faster to have some of it stored
     * directly on this object. You can choose, which attributes of the file-storage object you want to put on this object.
     * The system will use the attributes you provided, instead of the ones on the storage object.
     * For example, if you configure `"mime_type_attribute":"content_type"`, the system will use `your.App.YourObject.content_type`
     * instead of `your.App.YourStorage.MIMETYPE`. It is a good idea in general to override storage attributes, whenever a local
     * equivalent is available.
     *
     * @uxon-property override_file_attributes
     * @uxon-type metamodel:attribute[]
     * @uxon-template {"filename_attribute": "", "file_size_attribute": "", "mime_type_attribute": "", "time_created_attribute": "", "time_modified_attribute": ""}
     *
     * @param UxonObject $value
     * @return FileAttachmentBehavior
     *@throws BehaviorConfigurationError
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

        $eventSheet = $event->getDataSheet();

        // Ignore sheets, that are already scheduled for processing
        foreach ($this->pendingSheets as $p) {
            if ($p['dataSheet'] === $eventSheet) {
                return;
            }
        }

        // Strip any file-related columns, that cannot be saved directly to the attachment
        // object. They will be handled later
        $fileCols = [];
        $fileVals = [];
        $fileObj = $this->getFileObject();
        foreach ($eventSheet->getColumns() as $col) {
            if ($col->isAttribute() && $col->getAttribute()->isRelated() && $col->getAttribute()->getRelationPath()->getEndObject()->isExactly($fileObj)) {
                $fileCols[] = $col;
                $fileVals[] = $col->getValues();
                $eventSheet->getColumns()->remove($col);
            }
        }
        if (! empty($fileCols)) {
            $this->pendingSheets[] = [
                'dataSheet' => $eventSheet,
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
    public function onDataSave(OnCreateDataEvent|OnUpdateDataEvent $event)
    {
        // Ignore sheets produced by this behavior
        if ($this->inProgress === true) {
            return;
        }
        // Ignore other objects
        if (! $event->getDataSheet()->getMetaObject()->isExactly($this->getObject())) {
            return;
        }

        $eventSheet = $event->getDataSheet();

        // See if any file data was previously stripped from this attachment sheet (in `onBeforeDataSave`)
        $pending = null;
        $pendingKey = null;
        foreach ($this->pendingSheets as $i => $p) {
            if ($p['dataSheet'] === $eventSheet) {
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
        $leftKeyCol = $eventSheet->getColumns()->getByAttribute($fileRel->getLeftKeyAttribute());
        if (! $leftKeyCol) {
            // However, we can only read additional data if our attachment data has UIDs on
            // every row!
            if (! $eventSheet->hasUidColumn(true)) {
                throw new BehaviorRuntimeError($this, 'Cannot save files to related storage: cannot read relation key "' . $fileRel->getLeftKeyAttribute()->getAliasWithRelationPath() . '"!');
            }
            $attachmentSheet = DataSheetFactory::createFromObject($eventSheet->getMetaObject());
            $attachmentSheet->getColumns()->addFromSystemAttributes();
            $attachmentSheet->getFilters()->addConditionFromColumnValues($eventSheet->getUidColumn());
            $leftKeyCol = $attachmentSheet->getColumns()->addFromAttribute($fileRel->getLeftKeyAttribute());
            // At this point we know, that there was no path column. So we need to read it anyway. 
            if (null !== $pathExpr = $this->getFilePathCalculation()) {
                $calcPathCol = $attachmentSheet->getColumns()->addFromExpression($pathExpr);
                $attachmentSheet->dataRead();
                // Fill the path column with calculated paths on rows, that do not have a value yet. If they do,
                // leave it as it is because changing it would mean, the file would have to be moved.
                $pathColName = $leftKeyCol->getName();
                $calcColName = $calcPathCol->getName();
                foreach ($attachmentSheet->getRows() as $i => $row) {
                    if (! $row[$pathColName]) {
                        $attachmentSheet->setCellValue($pathColName, $i, $row[$calcColName]);
                    }
                }
                $attachmentSheet->getColumns()->remove($calcPathCol);
            } else {
                // If we do not have a calculation formula, just read the path column
                $attachmentSheet->dataRead();
            }
            // IMPORTANT: make sure, the freshly read data has the same row order, as the event
            // data. You never know for sure, how a file storage will sort the results by default!
            try {
                $attachmentSheet->sortLike($eventSheet);
            } catch (\Throwable $e) {
                throw new BehaviorRuntimeError($this, 'Cannot read required file attachment data to save the corresponding files', null, $e);
            }
        } else {
            // IDEA we actually do not need to save ALL the attachment data again here
            // It would be better just to save the file-related data. It would be better
            // to create a new sheet here and only take system columns and the $leftKeyCol
            // here.
            $attachmentSheet = $eventSheet;
        }

        foreach ($pending['fileCols'] as $i => $col) {
            $attachmentSheet->getColumns()->add($col);
            $newCol = $attachmentSheet->getColumns()->get($col->getName());
            $newCol->setValues($pending['fileVals'][$i]);
            if ($attachmentSheet !== $eventSheet) {
                $eventSheet->getColumns()->add($newCol);
            }
        }

        // Update the attachment object now that we know it has been created previously.
        // This update will contain the file contents and will lead to creation
        // of the file since we explicitly ask to create missing UIDs here.
        $attachmentSheet->dataUpdate(true, $event->getTransaction());
        // TODO the above update logic is actually called too many times if attachments are saved as subsheets.
        // Suppose we have an attachment already and add two more via ImageGallery. This will produce a subsheet
        // having one row without content, but with a UID, and two rows with content, but without UIDs. 
        // DataSheet::dataUpdate() will perform a create for the two new rows first, which will trigger this
        // code here. Then dataUpdate() will proceed with updating ALL rows - see #update-create-separation.
        // That will cause another call of this code and, thus, another update and another write on the same
        // file. Since many file systems are pretty slow, this has negative effect on upload processing performance!
        
        // The above update operation changes generated columns (in particular timestamping columns), so we need
        // to update the original $eventSheet to make sure it is still up to date.
        if ($attachmentSheet !== $eventSheet) {
            $eventSheet->importRows($attachmentSheet);
        }

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

    /**
     * @return ExpressionInterface|null
     */
    protected function getFilePathCalculation() : ?ExpressionInterface
    {
        if (is_string($this->filePathCalcExpression)) {
            $this->filePathCalcExpression = ExpressionFactory::createFromString($this->getWorkbench(), $this->filePathCalcExpression, $this->getObject());
        }
        return $this->filePathCalcExpression;
    }

    /**
     * A formula to calculate the file path from the values of other attributes
     *
     * @uxon-property file_path_calculation
     * @uxon-type formula
     *
     * @param string $expression
     * @return $this
     */
    protected function setFilePathCalculation(string $expression) : FileAttachmentBehavior
    {
        $this->filePathCalcExpression = $expression;
        return $this;
    }
}