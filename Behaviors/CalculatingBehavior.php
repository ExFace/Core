<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\DataSheets\DataSheetMapper;
use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Events\DataSheet\OnBeforeReadDataEvent;
use exface\Core\Events\DataSheet\OnCreateDataEvent;
use exface\Core\Events\DataSheet\OnReadDataEvent;
use exface\Core\Events\DataSheet\OnUpdateDataEvent;
use exface\Core\Factories\DataSheetMapperFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Interfaces\Events\DataTransactionEventInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;

/**
 * Applies calculations/formulas every time data read or written - e.g. to refresh attributes, hide them, etc.
 * 
 * Calculations can be performed on any data event: e.g.
 * 
 * - `exface.Core.DataSheet.OnReadData`
 * - `exface.Core.DataSheet.OnCreateData`
 * - `exface.Core.DataSheet.OnUpdateData`
 * - `exface.Core.DataSheet.OnBeforeCreateData`
 * - `exface.Core.DataSheet.OnBeforeUpdateData`
 * 
 * You can specify one or multiple events in `calculate_on_events`. If no events are specified,
 * calculations will be performed when reading data, that includes any of the calculated columns. 
 * 
 * ## Examples
 * 
 * ### Save a calculation result in a column
 * 
 * Lets say we have an object `my.App.DELIVERY` and we want to see a list of order numbers, that
 * are part of this delivery. We could do it with SQL, but that may be slow on large data sets.
 * We can also save the list of related orders in the delivery attribute `ORDER_NO_LIST` every time 
 * the delivery is updated.
 * 
 * ```
 * {
 *   "calculate_on_events": [
 *     "exface.Core.DataSheet.OnBeforeUpdateData"
 *   ],
 *   "calculations": [
 *     {
 *       "attribute_alias": "ORDER_NO_LIST",
 *       "calculation": "DELIVERY_POS__ORDER_POS__ORDER__NO:LIST_DISTINCT"
 *     }
 *   ]
 * }
 * 
 * ```
 * 
 * This will speed up readig deliveries, but has a couple of implications:
 * 
 * - We cannot read the orders when creating the delivery, because at that time it does not
 * have any positions yet and the relation to the orders goes over positions. A possible
 * solution would be a `CallActionBehavior` attached to `OnCreateData` of a delivery, that
 * will immediately update the data.
 * - Our calculation will not be performed if an individual position is changed, so it will
 * only work properly if the app only allows to edit the entire delivery at once - e.g. via
 * `DataSpreadSheet` widget.
 * - Our calculation will not be performed if things are changed on the DB - obviously, no
 * events will be fired. This should not happen anyway though ;)
 * 
 * ### Hide create/update username if a document is explicitly marked anonymous
 * 
 * This calculations will be performed when reading data sheets, that contain any of the
 * calculated columns.
 * 
 * ```
 * {
 *   "only_if_data_matches_conditions": {
 *     "operator": "AND",
 *     "conditions": [
 *       {"expression": "AnonymousFlag", "comparator": "==", "value": 1}
 *     ]
 *   },
 *   "calculations": [
 *     {
 *       "attribute_alias": "CREATED_BY",
 *       "calculation": "'Anonymous'"
 *     },
 *     {
 *       "attribute_alias": "CREATED_BY__LABEL",
 *       "calculation": "'Anonymous'"
 *     },
 *     {
 *       "attribute_alias": "MODIFIED_BY",
 *       "calculation": "=Calc(Status <= 20 ? 'Anonymous' : CREATED_BY)"
 *     },
 *     {
 *       "attribute_alias": "MODIFIED_BY__LABEL",
 *       "calculation": "=Calc(Status <= 20 ? 'Anonymous' : MODIFIED_BY__LABEL)"
 *     }
 *   ]
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class CalculatingBehavior extends AbstractBehavior
{
    private $onlyIfDataMatchesConditionGroupUxon = null;

    private $calculationsUxon = null;

    private $calculateOnEventNames = [];

    private $inProgress = false;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $eventMgr = $this->getWorkbench()->eventManager();
        if (! empty($this->calculateOnEventNames)) {
            foreach ($this->calculateOnEventNames as $eventName) {
                $eventMgr->addListener($eventName, [$this, 'onEventCalculate'], $this->getPriority());
            }
        } else {
            $eventMgr->addListener(OnReadDataEvent::getEventName(), [$this, 'onEventCalculate'], $this->getPriority());
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
        $eventMgr = $this->getWorkbench()->eventManager();
        if (! empty($this->calculateOnEventNames)) {
            foreach ($this->calculateOnEventNames as $eventName) {
                $eventMgr->removeListener($eventName, [$this, 'onEventCalculate']);
            }
        } else {
            $eventMgr->removeListener(OnReadDataEvent::getEventName(), [$this, 'onEventCalculate']);
        }
        
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        // TODO
        return $uxon;
    }

    /**
     * Executes the action if applicable
     * 
     * @param EventInterface $event
     * @return void
     */
    public function onEventCalculate(DataSheetEventInterface $event)
    {
        if ($this->isDisabled()) {
            return;
        }
        
        $inputSheet = $event->getDataSheet();
        
        // Do not do anything, if the base object of the widget is not the object with the behavior and is not
        // extended from it.
        if (! $inputSheet->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
		
        $logbook = new BehaviorLogBook($this->getAlias(), $this, $event);
        
        $logbook->addDataSheet('Event data', $inputSheet);
        $logbook->addLine('Reacting to event `' . $event::getEventName() . '`');
        $logbook->addLine('Found input data for object ' . $inputSheet->getMetaObject()->__toString());
        $logbook->setIndentActive(1);
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logbook));

        // Don't bother if this read is caused by the same behavior - e.g. loading additional data
        if ($this->inProgress === true) {
            $logbook->addLine('**Skipped** because of operation performed from within the same behavior (e.g. reading missing data)');
            $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
            return;
        }

        // Ignore empty sheets - nothing to calculate here!
        if ($inputSheet->isEmpty()) {
            return;
        }

        $onlyExistingCols = ! $this->willAddColumns($event);

        // Check if the data requires calculations - e.g. has any calculated columns
        if ($onlyExistingCols && $this->hasCalculatedColumns($inputSheet) === false) {
            $logbook->addLine('**Skipped** data does not contain any of the calculated attributes');
            $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
            return;
        }
        
        // Find rows, for which the behavior is relevant
        if ($this->hasRestrictionConditions()) {
            $logbook->addLine('Evaluating `only_if_data_matches_conditions`)');
            $logbook->addLine($this->getOnlyIfDataMatchesConditions()->__toString());

            // Make sure this behavior does not react to reads eventually performed while extracting data
            $this->inProgress = true;
            $filteredSheet = $inputSheet->extract($this->getOnlyIfDataMatchesConditions(), true);
            $this->inProgress = false;

            if ($filteredSheet->isEmpty()) {
                $logbook->addLine('**Skipped** because of `only_if_data_matches_conditions`');
                $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
                return;
            }
        } else {
            $filteredSheet = $inputSheet->copy();
        }

        if (! $inputSheet->hasUidColumn(true) && $this->willNeedToUpdateData($event)) {
            $logbook->addLine('**FAILED** because of input data has no or empty UID column!');
            $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
            return;
        }
        
        // Apply the calculation
        $this->inProgress = true;
        $mapper = $this->getDataMapper($inputSheet, $onlyExistingCols);
        $calculatedSheet = $mapper->map($filteredSheet, true, $logbook);

        $logbook->addDataSheet('Calculation data', $calculatedSheet);

        if ($this->willNeedToUpdateData($event)) {
            $logbook->addLine('Performing an update using the calculation data');
            $calculatedSheet->merge($inputSheet->extractSystemColumns(), false, true);
            $calculatedSheet->dataUpdate(false, ($event instanceof DataTransactionEventInterface) ? $event->getTransaction() : null);
        } else {
            $logbook->addLine('No update in data source required - all calculation can be directly applied to event data');
        }

        $logbook->addLine('Merging calculation data into event data overwriting previous values');
        $inputSheet->merge($calculatedSheet, true);
        $this->inProgress = false;

        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
    }

    /**
     * 
     * @param \exface\Core\Interfaces\Events\DataSheetEventInterface $event
     * @return bool
     */
    protected function willAddColumns(DataSheetEventInterface $event) : bool
    {
        return ! ($event instanceof OnReadDataEvent) && ! ($event instanceof OnBeforeReadDataEvent);
    }

    /**
     * 
     * @param \exface\Core\Interfaces\Events\DataSheetEventInterface $event
     * @return bool
     */
    protected function willNeedToUpdateData(DataSheetEventInterface $event) : bool
    {
        return $event instanceof OnCreateDataEvent || $event instanceof OnUpdateDataEvent;
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasRestrictionConditions() : bool
    {
        return $this->onlyIfDataMatchesConditionGroupUxon !== null;
    }
    
    /**
     * 
     * @return ConditionGroupInterface|NULL
     */
    protected function getOnlyIfDataMatchesConditions() : ?ConditionGroupInterface
    {
        if ($this->onlyIfDataMatchesConditionGroupUxon === null) {
            return null;
        }
        return ConditionGroupFactory::createFromUxon($this->getWorkbench(), $this->onlyIfDataMatchesConditionGroupUxon, $this->getObject());
    }
    
    /**
     * Only apply calculations to data rows, that match these conditions
     * 
     * @uxon-property only_if_data_matches_conditions
     * @uxon-type \exface\Core\CommonLogic\Model\ConditionGroup
     * @uxon-template {"operator": "AND","conditions":[{"expression": "","comparator": "==","value": ""}]}
     * 
     * @param UxonObject $uxon
     * @return CalculatingBehavior
     */
    protected function setOnlyIfDataMatchesConditions(UxonObject $uxon) : CalculatingBehavior
    {
        $this->onlyIfDataMatchesConditionGroupUxon = $uxon;
        return $this;
    }

    /**
     * List of calculations to apply
     * 
     * @uxon-property calculations
     * @uxon-type object
     * @uxon-template [{"attribute_alias": "", "calculation": "=Calc()"}]
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return \exface\Core\Behaviors\CalculatingBehavior
     */
    protected function setCalculations(UxonObject $uxon) : CalculatingBehavior
    {
        $this->calculationsUxon = $uxon;
        return $this;
    }

    /**
     * 
     * @param \exface\Core\Interfaces\DataSheets\DataSheetInterface $inputSheet
     * @return bool
     */
    protected function hasCalculatedColumns(DataSheetInterface $inputSheet) : bool
    {
        foreach ($this->calculationsUxon->getPropertiesAll() as $calcUxon) {
            $attrAlias = $calcUxon->getProperty('attribute_alias');
            // TODO what about attributes, that are relations? Do we need a configuration
            // to handle related columns somehow. If we change the value of the relation
            // itself, this impacts ALL attributes of the related object, right?
            foreach ($inputSheet->getColumns() as $col) {
                if ($col->isAttribute()) {
                    if ($col->getAttributeAlias() === $attrAlias) {
                        return true;
                    }
                }               
            }
        }
        return false;
    }

    /**
     * 
     * @param \exface\Core\Interfaces\DataSheets\DataSheetInterface $inputSheet
     * @return \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface
     */
    protected function getDataMapper(DataSheetInterface $inputSheet, bool $onlyExistingColumns = true) : DataSheetMapperInterface
    {
        $uxon = new UxonObject([
            'inherit_columns' => DataSheetMapper::INHERIT_NONE,
            'column_to_column_mappings' => [
                [
                    'from' => $this->getObject()->getUidAttributeAlias(),
                    'to' => $this->getObject()->getUidAttributeAlias()
                ]
            ]
        ]);
        foreach ($this->calculationsUxon->getPropertiesAll() as $calcUxon) {
            $attrAlias = $calcUxon->getProperty('attribute_alias');
            if ($onlyExistingColumns === true && ! $inputSheet->getColumns()->getByExpression($attrAlias)) {
                continue;
            }
            $mapUxon = new UxonObject([
                'from' => $calcUxon->getProperty('calculation'),
                'to' => $attrAlias
            ]);
            $uxon->appendToProperty('column_to_column_mappings', $mapUxon);
        }
        $mapper = DataSheetMapperFactory::createFromUxon($this->getWorkbench(), $uxon, $this->getObject(), $this->getObject());
        return $mapper;
    }
    
    /**
     * Names of data events, that should trigger the notification
     * 
     * If not specified, calculations will be performed after reading data, 
     * that includes any of the calculated columns - i.e. `exface.Core.DataSheet.OnReadData`. 
     * 
     * @uxon-property calculate_on_events
     * @uxon-type metamodel:event[]
     * @uxon-template ["exface.Core.DataSheet.OnReadData"]
     * 
     * @param \exface\Core\CommonLogic\UxonObject $arrayOfEvents
     * @return \exface\Core\Behaviors\CalculatingBehavior
     */
    public function setCalculateOnEvents(UxonObject $arrayOfEvents) : CalculatingBehavior
    {
        $this->calculateOnEventNames = $arrayOfEvents->toArray();
        return $this;
    }
}