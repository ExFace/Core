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
use exface\Core\DataTypes\ComparatorDataType;

/**
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
    
    private $overrideFileAttributes = [];
    
    private $pendingSheets = [];
    
    private $inProgress = false;
    
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
     * @return FileBehavior
     */
    protected function setTimeModifiedAttribute(string $value) : FileBehavior
    {
        $this->timeModifiedAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\Behaviors\FileBehaviorInterface::getMaxFileSizeInMb()
     */
    public function getMaxFileSizeInMb(): ?float
    {
        return $this->getFileBehavior()->getMaxFileSizeInMb();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\Behaviors\FileBehaviorInterface::getFolderAttribute()
     */
    public function getFolderAttribute(): ?MetaAttributeInterface
    {
        return $this->getFileBehavior()->getFolderAttribute();
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
        $mgr->addListener(OnBeforeDeleteDataEvent::getEventName(), [$this, 'onBeforeDataDelete']);
        $mgr->addListener(OnCreateDataEvent::getEventName(), [$this, 'onDataSave']);
        $mgr->addListener(OnUpdateDataEvent::getEventName(), [$this, 'onDataSave']);
        $mgr->addListener(OnDeleteDataEvent::getEventName(), [$this, 'onDataDelete']);
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
        $mgr->removeListener(OnBeforeDeleteDataEvent::getEventName(), [$this, 'onBeforeDataDelete']);
        $mgr->removeListener(OnCreateDataEvent::getEventName(), [$this, 'onDataSave']);
        $mgr->removeListener(OnUpdateDataEvent::getEventName(), [$this, 'onDataSave']);
        $mgr->removeListener(OnDeleteDataEvent::getEventName(), [$this, 'onDataDelete']);
        return $this;
    }
    
    /**
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
            ComparatorDataType::IN
        );
        
        $this->inProgress = false;
        
        $this->pendingSheets[] = [
            'dataSheet' => $ds,
            'deleteSheet' => $fileSheet
        ];
        return;
    }
    
    /**
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
        
        $fileRel = $this->getFileRelation();
        $leftKeyCol = $ds->getColumns()->getByAttribute($fileRel->getLeftKeyAttribute());
        if (! $leftKeyCol) {
            if (! $ds->hasUidColumn(true)) {
                throw new BehaviorRuntimeError($this, 'Cannot save files to related storage: cannot read relation key "' . $fileRel->getLeftKeyAttribute()->getAliasWithRelationPath() . '"!');
            }
            $attachmentSheet = DataSheetFactory::createFromObject($ds->getMetaObject());
            $attachmentSheet->getColumns()->addFromSystemAttributes();
            $leftKeyCol = $attachmentSheet->getColumns()->addFromAttribute($fileRel->getLeftKeyAttribute());
            $attachmentSheet->getFilters()->addConditionFromColumnValues($ds->getUidColumn());
            $attachmentSheet->dataRead();
        } else {
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
}