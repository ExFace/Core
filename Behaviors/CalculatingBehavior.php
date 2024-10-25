<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\DataSheets\DataSheetMapper;
use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Events\DataSheet\OnReadDataEvent;
use exface\Core\Factories\DataSheetMapperFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;

/**
 * Applies calculations/formulas every time data is read - e.g. to refresh attributes, hide them, etc.
 * 
 * ## Examples
 * 
 * ### Hide create/update username if a document is explicitly marked anonymous
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
 *       "calculation": "=Calc(Status <= 20 ? 'Anonymous' : UserNeu)"
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

    private $inProgress = false;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener(OnReadDataEvent::getEventName(), [$this, 'onReadAnonymize'], $this->getPriority());
        
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->removeListener(OnReadDataEvent::getEventName(), [$this, 'onReadAnonymize']);
        
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
    public function onReadAnonymize(OnReadDataEvent $event)
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
        
        $logbook->addDataSheet('Input data', $inputSheet);
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

        // Check if the data requires calculations - e.g. has any calculated columns
        if ($this->isApplicable($inputSheet) === false) {
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

        if (! $inputSheet->hasUidColumn(true)) {
            $logbook->addLine('**FAILED** because of input data has no or empty UID column!');
            $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
            return;
        }
        
        // Apply the calculation
        $this->inProgress = true;
        $mapper = $this->getDataMapper($inputSheet);
        $calculatedSheet = $mapper->map($filteredSheet, true, $logbook);
        $inputSheet->merge($calculatedSheet, true);
        $this->inProgress = false;

        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
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
    protected function isApplicable(DataSheetInterface $inputSheet) : bool
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
    protected function getDataMapper(DataSheetInterface $inputSheet) : DataSheetMapperInterface
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
            if (! $inputSheet->getColumns()->getByExpression($attrAlias)) {
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
}