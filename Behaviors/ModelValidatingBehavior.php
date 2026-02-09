<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\CommonLogic\Model\CustomAttribute;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\DataSheets\DataColumnListInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Widgets\iCanBeEditable;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Widgets\MessageList;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Events\Model\OnMetaObjectModelValidatedEvent;
use exface\Core\Events\Model\OnMetaAttributeModelValidatedEvent;
use exface\Core\Events\Action\OnActionPerformedEvent;
use exface\Core\Interfaces\Widgets\iHaveValue;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Interfaces\Tasks\ResultDataInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Events\Model\OnBehaviorModelValidatedEvent;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\Model\ConditionInterface;
use Throwable;

/**
 * This behavior validates the model when an editor is opened for the object, it is attached to.
 * 
 * Apart from built-in validation, this behavior will dispatch the following events allowing
 * third-party code to hook in additional validation logic or even to modify the editors
 * 
 * - `OnMetaObjectModelValidated` will be fired when an editor for a meta object is opened
 * - `OnMetaAttributeModelValidated` will be fired when an editor for an attribute is opened
 * 
 * All events are fired after the built-in validation is complete. Refer to the StateMachineBehavior
 * for an example on how these events can be used.
 * 
 * @author Andrej Kabachnik
 *
 */
class ModelValidatingBehavior extends AbstractBehavior
{
    private const CORE_ATTRIBUTE = 'exface.core.ATTRIBUTE';
    private const CORE_ATTRIBUTE_GROUP_ATTRIBUTES = 'exface.core.ATTRIBUTE_GROUP_ATTRIBUTES';
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $evtMgr = $this->getWorkbench()->eventManager();

        // Add messages to model editors
        $evtMgr->addListener(OnActionPerformedEvent::getEventName(), [$this, 'onObjectEditorDialog'], $this->getPriority());
        $evtMgr->addListener(OnActionPerformedEvent::getEventName(), [$this, 'onAttributeEditorDialog'], $this->getPriority());
        $evtMgr->addListener(OnActionPerformedEvent::getEventName(), [$this, 'onBehaviorEditorDialog'], $this->getPriority());
        
        // Add checks when saving model data
        $evtMgr->addListener(OnActionPerformedEvent::getEventName(), [$this, 'onModelDataSave'], $this->getPriority());

        // When reading attributes, add generated and inherited ones
        $evtMgr->addListener(OnActionPerformedEvent::getEventName(), [$this, 'onObjectEditorReadAttributes'], $this->getPriority());
        
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $evtMgr = $this->getWorkbench()->eventManager();

        $evtMgr->removeListener(OnActionPerformedEvent::getEventName(), [$this, 'onObjectEditorDialog']);
        $evtMgr->removeListener(OnActionPerformedEvent::getEventName(), [$this, 'onAttributeEditorDialog']);
        $evtMgr->removeListener(OnActionPerformedEvent::getEventName(), [$this, 'onBehaviorEditorDialog']);

        $evtMgr->removeListener(OnActionPerformedEvent::getEventName(), [$this, 'onModelDataSave']);
        
        $evtMgr->removeListener(OnActionPerformedEvent::getEventName(), [$this, 'onObjectEditorReadAttributes']);
        
        return $this;
    }
    
    public function onModelDataSave(OnActionPerformedEvent $event)
    {
        $action = $event->getAction();
        
        if (! ($action->is('exface.Core.SaveData'))) {
            return;
        }
        
        $result = $event->getResult();
        if (! ($result instanceof ResultDataInterface) || ! $result->hasData()) {
            return;
        }
        
        $model = $this->getWorkbench()->model();
        
        if ($result->getData()->getMetaObject()->is('exface.Core.OBJECT')) {
            $data = $result->getData();
            if (false === $data->hasUidColumn(true)) {
                return;
            }
            foreach ($data->getUidColumn()->getValues(false) as $objectUid) {
                try {
                    $object = $model->getObject($objectUid);
                    $object = $model->reloadObject($object);
                    $messageList = WidgetFactory::create(UiPageFactory::createBlank($this->getWorkbench(), ''), 'MessageList');
                    $this->validateObject($object, $messageList);
                    //$this->getWorkbench()->eventManager()->dispatch(new OnMetaObjectModelValidatedEvent($object, $messageList));
                } catch (\Throwable $e) {
                    $code = ($e instanceof ExceptionInterface) ? $e->getAlias() : null;
                    
                    $objectData = $data->getRow($data->getUidColumn()->findRowByValue($objectUid));
                    if ($objectData['NAME'] !== null) {
                        $objectText = '"' . $objectData['NAME'] . '" ';
                    }
                    $objectText .= '[' . ($objectData['ALIAS'] ?? $objectData['UID']) . ']';
                    
                    throw new RuntimeException('Error in object ' . $objectText . ': ' . $e->getMessage(), $code, $e);
                }
            }
        }
        
        if ($result->getData()->getMetaObject()->is(self::CORE_ATTRIBUTE)) {
            $data = $result->getData();
            if (! $objectCol = $data->getColumns()->get('OBJECT')) {
                return;
            }
            foreach ($data->getRows() as $rownr => $row) {
                $objectUid = $objectCol->getCellValue($rownr);
                $attributeAlias = $row['ALIAS'];
                try {
                    $object = $model->getObject($objectUid);
                    $object = $model->reloadObject($object);
                    $attribute = $object->getAttribute($attributeAlias);
                    $messageList = WidgetFactory::create(UiPageFactory::createBlank($this->getWorkbench(), ''), 'MessageList');
                    $this->validateAttribute($attribute, $messageList);
                    //$this->getWorkbench()->eventManager()->dispatch(new OnMetaAttributeModelValidatedEvent($attribute, $messageList));
                } catch (\Throwable $e) {
                    $code = ($e instanceof ExceptionInterface) ? $e->getAlias() : null;
                    
                    $row = $data->getRow($data->getUidColumn()->findRowByValue($objectUid));
                    if ($row['NAME'] !== null) {
                        $attrText = '"' . $row['NAME'] . '" ';
                    }
                    $attrText .= '[' . ($row['ALIAS'] ?? $row['UID']) . ']';
                    
                    if ($object instanceof MetaObjectInterface) {
                        $objectText = '"' . $object->getName() . '" [' . $object->getAliasWithNamespace() . ']';
                    } else {
                        $objectText = $row['OBJECT'];
                    }
                    
                    throw new RuntimeException('Error in attribute ' . $attrText . ' of object ' . $objectText . ': ' . $e->getMessage(), $code, $e);
                }
            }
        }
        return;
    }

    /**
     * Adds inherited and generated attributes when reading attribute data for the object editor
     * 
     * @param \exface\Core\Events\Action\OnActionPerformedEvent $event
     */
    public function onObjectEditorReadAttributes(OnActionPerformedEvent $event)
    {
        $action = $event->getAction();       
        // Only handle ReadData actions for ATTRIBUTE object triggered for table widgets
        if (! $action->is('exface.Core.ReadData')) {
            return;
        }
        if (!$this->appliesToObject($action->getMetaObject())) {
            return;
        }
        if (! $event->getTask()->isTriggeredByWidget()) {
            return;
        }
        $widget = $event->getTask()->getWidgetTriggeredBy();
        // Ignore editable tables or non-table widgets. Our attributes are not editable anyway
        if ($widget->getWidgetType() !== 'DataTable' || ($widget instanceof iCanBeEditable && $widget->isEditable())) {
            return;
        }
        // Ignore paged tables because adding rows will break pagination
        if ($widget->isPaged()) {
            return;
        }

        // Now get the regular result of reading data
        if (! $event->getResult() instanceof ResultDataInterface) { 
            return;
        }
        /** @var \exface\Core\Interfaces\DataSheets\DataSheetInterface $resultSheet */
        $resultSheet = $event->getResult()->getData();
        $resultObject = $resultSheet->getMetaObject();
        if (! $this->appliesToObject($resultObject) || ! $resultSheet->getAggregations()->isEmpty()) {
            return;
        }

        // Attempt to generate additional rows for all attributes, that are not present in the data
        // Return the original data if anything goes wrong.
        try {
            $outputSheet = null;
            
            switch (true) {
                // Add inherited and custom attributes.
                case $resultSheet->getMetaObject()->isExactly(self::CORE_ATTRIBUTE):
                    $outputSheet = $this->addAttributesToAttributeData($resultSheet);
                    break;
                // Add custom attributes to attribute group.
                case $resultSheet->getMetaObject()->isExactly(self::CORE_ATTRIBUTE_GROUP_ATTRIBUTES):
                    $outputSheet = $this->addAttributesToGroupData($resultSheet);
                    break;
            }
            
            if ($outputSheet !== null && ! $outputSheet->isEmpty()) {
                if ($originCol = $resultSheet->getColumns()->getByExpression('ORIGIN')) {
                    $originCol->setValueOnAllRows(1);
                }
                // Apply the filters of the original sheet to the additional data
                $outputSheet = $outputSheet->extract($outputSheet->getFilters());
                // Append remaining rows to the original data
                foreach ($outputSheet->getRows() as $row) {
                    $resultSheet->addRow($row, false, false);
                }
            }
        } catch (Throwable $e) {
            $this->getWorkbench()->getLogger()->logException(new BehaviorRuntimeError($this, 'Cannot add inherited/virtual attributes to object attribute data. ' . $e->getMessage(), null, $e));
        }
    }

    /**
     * Returns TRUE if this Behavior applies to a given metaobject.
     * 
     * @param MetaObjectInterface $object
     * @return bool
     */
    protected function appliesToObject(MetaObjectInterface $object) : bool 
    {
        return 
            $object->isExactly(self::CORE_ATTRIBUTE) ||
            $object->isExactly(self::CORE_ATTRIBUTE_GROUP_ATTRIBUTES);
    }

    /**
     * Copy the input sheet and remove all filters that match the callback.
     * 
     * @param DataSheetInterface $eventResultSheet
     * @param callable           $conditionsFilter
     * @return DataSheetInterface
     */
    protected function getOutputSheet(
        DataSheetInterface $eventResultSheet,
        callable           $conditionsFilter
    ) : DataSheetInterface
    {
        // Create a separate data sheet for the results in order not to break the regular data
        $outputSheet = $eventResultSheet->copy()->removeRows();
        foreach ($outputSheet->getFilters()->getConditions($conditionsFilter) as $cond) {
            $outputSheet->getFilters()->removeCondition($cond);
        }
        
        return $outputSheet;
    }

    /**
     * Return the value of the first filter from a given datasheet that matches the callable.
     * 
     * @param DataSheetInterface $dataSheet
     * @param callable           $conditionSearcher
     * @return mixed
     */
    protected function findFilterValue(DataSheetInterface $dataSheet, callable $conditionSearcher) : mixed
    {
        $objFilters = $dataSheet->getFilters()->getConditions($conditionSearcher);

        foreach ($objFilters as $cond) {
            $comp = $cond->getComparator();
            if (($comp === ComparatorDataType::IS || $comp === ComparatorDataType::EQUALS) && $cond->isEmpty() === false) {
                $value = $cond->getValue();
                if($value !== null) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @param DataSheetInterface $eventResultSheet
     * @return DataSheetInterface|null
     */
    protected function addAttributesToAttributeData(
        DataSheetInterface         $eventResultSheet,
    ) : ?DataSheetInterface
    {
        // Find target object, by extracting it from filters.
        $objConditionSearcher = function(ConditionInterface $condition) {
            $expr = $condition->getLeftExpression();
            return
                $expr->isMetaAttribute() &&
                $expr->getMetaObject()->is(self::CORE_ATTRIBUTE) &&
                $expr->getAttribute()->getAliasWithRelationPath() === 'OBJECT';
        };

        $objUid = $this->findFilterValue($eventResultSheet, $objConditionSearcher);
        if($objUid === null) {
            return null;
        }
        
        $object = MetaObjectFactory::createFromUid($this->getWorkbench(), $objUid);
        $outputSheet = $this->getOutputSheet($eventResultSheet, $objConditionSearcher);
        $aliasCol = $eventResultSheet->getColumns()->getByExpression('ALIAS');
        $parentAttrs = $this->getParentAttributeAliases($object);
        
        foreach ($object->getAttributes() as $attr) {
            $attrRowIdx = $aliasCol->findRowByValue($attr->getAlias());
            // For attributes, that are already in the data sheet, just add the icons and skip the rest
            if ($attrRowIdx !== false) {
                $eventResultSheet->setCellValue('INFO_ICONS', $attrRowIdx, $this->buildHtmlAttributeInfoIcons($attr, $parentAttrs));
                continue;
            }
            // Also skip the generated LABEL attribute because it is very confusing if it is there
            // right next to the regular attribute with label-flag
            if ($attr->getAlias() === MetaAttributeInterface::OBJECT_LABEL_ALIAS && ! $attr->isInherited()) {
                continue;
            }
            
            $this->addAttributeToData($outputSheet, $attr, $eventResultSheet->getColumns());
        }
        
        return $outputSheet;
    }

    /**
     * @param DataSheetInterface $eventResultSheet
     * @return DataSheetInterface|null
     */
    protected function addAttributesToGroupData(
        DataSheetInterface         $eventResultSheet,
    ) : ?DataSheetInterface
    {
        $conditionSearcher = function(ConditionInterface $condition) {
            $expr = $condition->getLeftExpression();
            return
                $expr->isMetaAttribute() &&
                $expr->getMetaObject()->is(self::CORE_ATTRIBUTE_GROUP_ATTRIBUTES) &&
                $expr->getAttribute()->getAliasWithRelationPath() === 'ATTRIBUTE_GROUP';
        };

        $groupUid = $this->findFilterValue($eventResultSheet, $conditionSearcher);
        if($groupUid === null) {
            return null;
        }
        
        $groupObject = MetaObjectFactory::createFromString($this->getWorkbench(), 'exface.core.ATTRIBUTE_GROUP');
        $groupSheet = DataSheetFactory::createFromObject($groupObject);
        $groupSheet->getColumns()->addFromUidAttribute();
        $groupSheet->getColumns()->addFromExpression('OBJECT');
        $groupSheet->getColumns()->addFromExpression('ALIAS_WITH_NS');
        $groupSheet->getFilters()->addConditionFromAttribute($groupObject->getUidAttribute(), $groupUid, '==');
        $groupSheet->dataRead();
        $groupRow = $groupSheet->getRow();
        if($groupRow === null) {
            return null;
        }

        $targetObjectUID = $groupRow['OBJECT'];
        $groupAlias = $groupRow['ALIAS_WITH_NS'];
        if($targetObjectUID === null || $groupAlias == null) {
            return null;
        }

        $relString = 'ATTRIBUTE__';
        $aliasCol = $eventResultSheet->getColumns()->getByExpression($relString . 'ALIAS');
        $targetObject = MetaObjectFactory::createFromUid($this->getWorkbench(), $targetObjectUID);
        $outputSheet = $this->getOutputSheet($eventResultSheet, $conditionSearcher);

        foreach ($targetObject->getAttributeGroup($groupAlias) as $attr) {
            if(!$attr instanceof CustomAttribute) {
                continue;
            }
            
            if ($aliasCol->findRowByValue($attr->getAlias()) !== false) {
                continue;
            }

            $this->addAttributeToData($outputSheet, $attr, $eventResultSheet->getColumns(), $relString);
        }
        
        return $outputSheet;
    }

    /**
     * Add an attribute to a given output sheet.
     *
     * @param DataSheetInterface $dataSheet
     * @param MetaAttributeInterface $attr
     * @param DataColumnListInterface|DataColumnInterface[] $columnsToFill
     * @param string $relationPath
     * @return void
     */
    protected function addAttributeToData(
        DataSheetInterface              $dataSheet,
        MetaAttributeInterface          $attr,
        DataColumnListInterface|array   $columnsToFill,
        string                          $relationPath = ''
    ) : void
    {
        $row = [];
        
        foreach ($columnsToFill as $col) {
            switch ($col->getExpressionObj()->__toString()) {
                case $relationPath . 'UID':
                    $row[$col->getName()] = $attr->getId();
                    break;
                case $relationPath . 'NAME':
                    $row[$col->getName()] = $attr->getName();
                    break;
                case $relationPath . 'ALIAS':
                    $row[$col->getName()] = $attr->getAlias();
                    break;
                case $relationPath . 'DISPLAYORDER':
                    $row[$col->getName()] = $attr->getDefaultDisplayOrder();
                    break;
                case $relationPath . 'DATA_ADDRESS':
                    $row[$col->getName()] = $attr->getDataAddress();
                    break;
                case $relationPath . '=Left(DATA_ADDRESS,60)':
                    $row[$col->getName()] = StringDataType::truncate($attr->getDataAddress(), 60);
                    break;
                case $relationPath . 'TYPE':
                    $row[$col->getName()] = $attr->getType();
                    break;
                case $relationPath . 'DATATYPE__LABEL':
                    $row[$col->getName()] = $attr->getDataType()->getName();
                    break;
                case $relationPath . 'EDITABLEFLAG':
                    $row[$col->getName()] = $attr->isEditable() ? 1 : 0;
                    break;
                case $relationPath . 'REQUIREDFLAG':
                    $row[$col->getName()] = $attr->isReadable() ? 1 : 0;
                    break;
                case $relationPath . 'RELATED_OBJ__NAME':
                    $row[$col->getName()] = $attr->isRelation() ? $attr->getRelation()->getRightObject()->getName() : null;
                    break;
                case $relationPath . 'HIDDENFLAG':
                    $row[$col->getName()] = $attr->isHidden() ? 1 : 0;
                    break;
                case $relationPath . 'UIDFLAG':
                    $row[$col->getName()] = $attr->isUidForObject() ? 1 : 0;
                    break;
                case $relationPath . 'LABELFLAG':
                    $row[$col->getName()] = $attr->isLabelForObject() ? 1 : 0;
                    break;
                case $relationPath . 'SHORT_DESCRIPTION':
                    $row[$col->getName()] = $attr->getShortDescription();
                    break;
                case $relationPath . 'ORIGIN':
                    $row[$col->getName()] = $attr->getOrigin();;
                    break;
                case $relationPath . 'INFO_ICONS':
                    $row[$col->getName()] = $this->buildHtmlAttributeInfoIcons($attr);
                    break;
            }
        }
        
        if(!empty($row)) {
            $dataSheet->addRow($row, false, false);
        }
    }
    
    /**
     * Dispatches xxxModelValidatedEvents when editor-dialogs are opened for meta objects or attributes.
     * 
     * @triggers \exface\Core\Events\DataSheet\OnMetaObjectModelValidatedEvent
     * 
     * @param DataSheetEventInterface $event
     * @return void
     */
    public function onObjectEditorDialog(OnActionPerformedEvent $event)
    {
        $action = $event->getAction();
        if (! ($action->is('exface.Core.ShowObjectEditDialog'))) {
            return;
        }
        /** @var \exface\Core\Actions\ShowObjectEditDialog $action */
        if (! $action->getMetaObject()->is('exface.Core.OBJECT')) {
            return;
        }

        /* @var $widget \exface\Core\Widgets\Container */
        $widget = $action->getWidget();
        foreach ($widget->getWidgetsRecursive() as $child) {
            if (($child instanceof iShowSingleAttribute) && ($child instanceof iHaveValue)) {
                $attrAlias = $child->getAttributeAlias();
                if (($attrAlias === 'UID' || $attrAlias === 'ALIAS')) {
                    if ($child->hasValue() === false) {
                        break;
                    }
                    try {
                        $object = $this->getWorkbench()->model()->getObject($child->getValue());
                        $this->validateObject($object, $widget->getMessageList());
                        $this->getWorkbench()->eventManager()->dispatch(new OnMetaObjectModelValidatedEvent($object, $widget->getMessageList()));
                    } catch (\Throwable $e) {
                        $code = ($e instanceof ExceptionInterface) ? ': error ' . $e->getAlias() : '';
                        $widget->getMessageList()->addError($e->getMessage(), 'Failed loading meta object' . $code . '!');
                        $this->getWorkbench()->getLogger()->logException($e);
                    }
                    break;
                }
            }
        }        
    }

    /**
     * Dispatches xxxModelValidatedEvents when editor-dialogs are opened for meta objects or attributes.
     * 
     * @triggers \exface\Core\Events\DataSheet\OnMetaAttributeModelValidatedEvent
     * 
     * @param DataSheetEventInterface $event
     * @return void
     */
    public function onAttributeEditorDialog(OnActionPerformedEvent $event)
    {
        $action = $event->getAction();
        if (! ($action->is('exface.Core.ShowObjectEditDialog'))) {
            return;
        }
        /** @var \exface\Core\Actions\ShowObjectEditDialog $action */
        if (! $action->getMetaObject()->isExactly(self::CORE_ATTRIBUTE)) {
            return;
        }

        $widget = $action->getWidget();
        $foundObject = false;
        $foundAttribute = false;
        foreach ($widget->getWidgetsRecursive() as $child) {
            if (($child instanceof iShowSingleAttribute) && ($child instanceof iHaveValue)) {
                $attrAlias = $child->getAttributeAlias();
                if (($attrAlias === 'OBJECT')) {
                    if ($child->hasValue() === false) {
                        break;
                    }
                    $foundObject = true;
                    try {
                        $object = $this->getWorkbench()->model()->getObject($child->getValue());
                    } catch (\Throwable $e) {
                        $this->getWorkbench()->getLogger()->logException($e);
                    }
                }
                if (($attrAlias === 'ALIAS')) {
                    if ($child->hasValue() === false) {
                        break;
                    }
                    $foundAttribute = true;
                    try {
                        $attribute = $object->getAttribute($child->getValue());
                    } catch (\Throwable $e) {
                        $this->getWorkbench()->getLogger()->logException($e);
                    }
                }
                
                if ($foundAttribute === true && $foundObject === true && $attribute !== null) {
                    $this->getWorkbench()->eventManager()->dispatch(new OnMetaAttributeModelValidatedEvent($attribute, $widget->getMessageList()));
                    break;
                }
            }
        }
    }

    /**
     * Dispatches xxxModelValidatedEvents when editor-dialogs are opened for meta objects or attributes.
     * 
     * @triggers \exface\Core\Events\DataSheet\OnBehaviorModelValidatedEvent
     * 
     * @param DataSheetEventInterface $event
     * @return void
     */
    public function onBehaviorEditorDialog(OnActionPerformedEvent $event)
    {
        $action = $event->getAction();
        
        if (! ($action->is('exface.Core.ShowObjectEditDialog'))) {
            return;
        }        
        /** @var \exface\Core\Actions\ShowObjectEditDialog $action */
        if (! $action->getMetaObject()->isExactly('exface.Core.OBJECT_BEHAVIORS') || ! $this->getObject()->isExactly('exface.Core.OBJECT_BEHAVIORS')) {
            return;
        }

        $widget = $action->getWidget();
        $foundObject = null;
        $foundBehavior = null;
        foreach ($widget->getWidgetsRecursive() as $child) {
            if (($child instanceof iShowSingleAttribute) && ($child instanceof iHaveValue)) {
                $attrAlias = $child->getAttributeAlias();
                if (($attrAlias === 'OBJECT')) {
                    if ($child->hasValue() === false) {
                        break;
                    }
                    $foundObject = $child->getValue();
                }
                if (($attrAlias === 'UID')) {
                    if ($child->hasValue() === false) {
                        break;
                    }
                    $foundBehavior = $child->getValue();
                }
                
                if ($foundBehavior !== null && $foundObject !== null) {
                    break;
                }
            }
        }
        if ($foundBehavior !== null && $foundObject !== null) {
            try {
                $object = $this->getWorkbench()->model()->getObject($foundObject);
                $behavior = $object->getBehaviors()->getByUid($foundBehavior);
                $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorModelValidatedEvent($behavior, $widget->getMessageList()));
            } catch (\Throwable $e) {
                $this->getWorkbench()->getLogger()->logException($e);
            }
        }
    }
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @param MessageList $messageList
     * @return \Throwable[]
     */
    protected function validateObject(MetaObjectInterface $object, MessageList $messageList) : array
    {
        $exceptions = [];
        try {
            $this->validateObjectAttributes($object, $messageList);
        } catch (\Throwable $e) {
            $exceptions[] = $e;
        }
        try {
            $this->validateObjectUid($object, $messageList);
        } catch (\Throwable $e) {
            $exceptions[] = $e;
        }
        try {
            $this->validateObjectLabel($object, $messageList);
        } catch (\Throwable $e) {
            $exceptions[] = $e;
        }
        try {
            $this->validateObjectDataSource($object, $messageList);
        } catch (\Throwable $e) {
            $exceptions[] = $e;
        }
        return $exceptions;
    }
    
    protected function validateAttribute(MetaAttributeInterface $attribute, MessageList $messageList)
    {
        // Try to load all the lazy loaded sub-models - they might throw an error!
        $attribute->getDataType();
        $attribute->getDefaultEditorUxon();
        $attribute->getDefaultDisplayUxon();
        
        // TODO add more validation for attributes
        return;
    }
    
    protected function validateObjectUid(MetaObjectInterface $object, MessageList $messageList)
    {
        if ($object->hasUidAttribute() === false) {
            $messageList->addMessageByCode('734GQRL', 'Object as no UID attribute!');
        }
    }
    
    protected function validateObjectLabel(MetaObjectInterface $object, MessageList $messageList)
    {
        if ($object->hasLabelAttribute() === false) {
            $messageList->addMessageByCode('734GDAX', 'Object has no LABEL attribute!');
        } else {
            $labels = [];
            foreach ($object->getAttributes() as $attr) {
                if ($attr->isLabelForObject()) {
                    $labels[] = $attr;
                }
            }
            
            if (count($labels) > 1) {
                $messageList->addMessageByCode('73A6BVD', 'Object has multiple LABEL attributes!');
            }
        }
    }
    
    protected function validateObjectDataSource(MetaObjectInterface $object, MessageList $messageList)
    {
        if (($object->getDataAddress() === null ||  $object->getDataAddress() === '' || $object->hasDataSource() === false) && $object->isReadable()) {
            $messageList->addMessageByCode('734GUW2', 'Object without a data source cannot be readable!');
        }
    }
    
    protected function validateObjectAttributes(MetaObjectInterface $object, MessageList $messageList)
    {
        if ($object->getAttributes()->isEmpty()) {
            $messageList->addMessageByCode('734GWLL', 'Object has no attributes!');
        }
        
        if ($object->isReadable() === false && $object->getAttributes()->getReadable()->isEmpty() === false) {
            $messageList->addMessageByCode('734GZDR', 'Object is not readable, but has readable attributes!');
        }
    }
    
    protected function translate(string $messageId, array $placeholderValues = null, float $pluralNumber = null) : string
    {
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate($messageId, $placeholderValues, $pluralNumber);
    }

    /**
     * 
     * @param \exface\Core\Interfaces\Model\MetaAttributeInterface $attr
     * @return string
     */
    protected function buildHtmlAttributeInfoIcons(MetaAttributeInterface $attr, array $parentAttributes = []) : string
    {
        $html = '';
        if ($attr->isInherited()) {
            $html .= '<i class="fa fa-arrow-circle-up" title="Inherited from ' . str_replace('"', '', $attr->getObjectInheritedFrom()->__toString()) . '"></i>';
        } elseif (null !== $parentAttr = $parentAttributes[$attr->getAlias()]) {
            $html .= '<i class="fa fa-arrow-circle-down" title="Overwritten from ' . str_replace('"', '', $parentAttr->getObject()->__toString()) . '"></i>';
        }
        if ($attr instanceof CustomAttribute) {
            $html .= '<i class="fa fa-user-circle-o" title="Custom attribute from ' . str_replace('"', "'", $attr->getSourceHint()) . '"></i>';
        }
        return $html;
    }

    /**
     * Returns an array of parent attributes inherited or overwritten by this object with their aliases as keys.
     *
     * For attributes, that are inherited multiple times, the version closest to this object is returned.
     *
     * @param MetaObjectInterface $obj
     * @return array<string, MetaAttributeInterface>
     */
    protected function getParentAttributeAliases(MetaObjectInterface $obj) : array
    {
        $aliases = [];
        foreach ($obj->getParentObjects() as $parent) {
            foreach ($parent->getAttributes()->getAll() as $attr) {
                $aliases[$attr->getAlias()] = $attr;
            }
        }
        return $aliases;
    }
}