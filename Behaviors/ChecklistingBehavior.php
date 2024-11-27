<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\DataSheets\DataCheckWithOutputData;
use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;
use exface\Core\CommonLogic\Model\Behaviors\BehaviorDataCheckList;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\DataSheets\DataCheckFailedErrorMultiple;
use exface\Core\Interfaces\DataSheets\DataCheckListInterface;

/**
 * Applies a checklist to the input data and persists the results at a configurable data address.
 * 
 * Whenever a data-item matches any of the conditions on the checklist, that condition will save a checklist item.
 * Checklist items can be warnings, hints, errors - anything, that is not critical, but important to see for the user.
 * 
 * The checklist itself should be stored in the data source, that holds the checked object. After all, checklist items are
 * bits of information about this object at a certain point in time, so they should be handled (e.g. backed up) together.
 * 
 * This behavior is similar to the `ValidatingBehavior` except for the result of the checks: in contrast to the
 * `ValidatingBehavior`, that produces errors if at least one condition was matched, the `ChecklistingBehavior` merely saves
 * its findings to the data source allowing the user to deal with them separately.
 * 
 * ## Examples
 * 
 * ```
 *  {
 *      "check_on_update": [{
 *          "output_data_sheet": {
 *              "object_alias": "my.APP.CHECKLIST",
 *              "rows": [{
 *                  "CRITICALITY": "0",
 *                  "LABEL": "Error",
 *                  "MESSAGE": "This order includes products, that are not available for ordering yet!",
 *                  "COLOR": "red",
 *                  "ICON":"sap-icon://message-warning"
 *              }]     
 *          },
 *          "operator": "AND",
 *          "conditions": [{
 *              "expression": "[#ORDER_POS__PRODUCT__LIFECYCLE_STATE:MIN#]",
 *              "comparator": "<",
 *              "value": "50"
 *          }]
 *       }]
 *  }
 * 
 * ```
 * 
 * @author Andrej Kabachnik, Georg Bieger
 * 
 */
class ChecklistingBehavior extends AbstractValidatingBehavior
{
    protected function generateDataChecks(UxonObject $uxonObject): DataCheckListInterface
    {
        $dataCheckList = new BehaviorDataCheckList($this->getWorkbench(), $this);
        foreach ($uxonObject as $uxon) {
            $dataCheckList->add(new DataCheckWithOutputData($this->getWorkbench(), $uxon));
        }
        
        return $dataCheckList;
    }


    protected function processValidationResult(DataCheckFailedErrorMultiple $result, BehaviorLogBook $logbook): void
    {
        $outputSheets = [];
        $affectedUidAliases = [];
        
        foreach ($result->getAllErrors() as $error) {
            $check = $error->getCheck();
            if(!$check instanceof DataCheckWithOutputData) {
                continue;
            }

            if(!$checkOutputSheet = $check->getOutputDataSheet()) {
                continue;
            }
            
            $metaObjectAlias = $checkOutputSheet->getMetaObject()->getAlias();
            if(key_exists($metaObjectAlias,$outputSheets)) {
                $outputSheets[$metaObjectAlias]->addRows($checkOutputSheet->getRows());
            } else {
                // We need to maintain separate sheets for each MetaObjectAlias, in case the designer
                // configured data checks associated with different MetaObjects.
                $outputSheets[$metaObjectAlias] = $checkOutputSheet;
                $affectedUidAliases[$metaObjectAlias] = $check->getAffectedUidAlias();
            }
        }
        
        $logbook->addLine('Processing output data sheets...');
        $logbook->addIndent(1);
        foreach ($outputSheets as $metaObjectAlias => $outputSheet) {
            if($outputSheet === null || $outputSheet->countRows() === 0) {
                continue;
            }

            $logbook->addDataSheet('Output-'.$metaObjectAlias, $outputSheet);
            $logbook->addLine('Working on sheet for '.$metaObjectAlias.'...');
            $affectedUidAlias = $affectedUidAliases[$metaObjectAlias];
            $logbook->addLine('Affected UID-Alias is '.$affectedUidAlias.'.');
            // We filter by affected UID rather than by native UID to ensure that our delete operation finds all cached outputs,
            // especially if they were part of the source transaction.
            $outputSheet->getFilters()->addConditionFromValueArray($affectedUidAlias, $outputSheet->getColumnValues($affectedUidAlias));
            // We want to delete ALL entries for any given affected UID to ensure that the cache only contains outputs
            // that actually matched the current round of validations. This way we essentially clean up stale data.
            $deleteSheet = $outputSheet->copy();
            // Remove the UID column, because otherwise dataDelete() ignores filters and goes by UID.
            $deleteSheet->getColumns()->remove($deleteSheet->getUidColumn());
            $logbook->addLine('Deleting data with affected UIDs from cache.');
            $count = $deleteSheet->dataDelete();
            $logbook->addLine('Deleted '.$count.' lines from cache.');
            // Finally, write the most recent outputs to the cache.
            $logbook->addLine('Writing data to cache.');
            $count = $outputSheet->dataUpdate(true);
            $logbook->addLine('Added '.$count.' lines to cache.');
        }
        $logbook->addIndent(-1);
    }

    /**
     * Triggers only when data is being CREATED.
     * 
     *  ### Placeholders:
     * 
     *  - `[#~new:alias#]`: Loads the value the specified alias will hold AFTER the event has been applied.
     * 
     * @uxon-property check_on_create
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataCheckWithOutputData[]
     * @uxon-template [{"output_data_sheet":{"object_alias": "", "rows": [{"CRITICALITY":"0", "LABELS":"", "MESSAGE":"", "COLOR":"", "ICON":"sap-icon://message-warning"}]}, "operator": "AND", "conditions": [{"expression": "", "comparator": "", "value": ""}]}]
     * 
     * @param UxonObject $uxon
     * @return AbstractValidatingBehavior
     */
    public function setCheckOnCreate(UxonObject $uxon) : AbstractValidatingBehavior
    {
        $this->setUxonForEventContext($uxon,self::CONTEXT_ON_CREATE);
        return $this;
    }

    /**
     * Triggers only when data is being UPDATED.
     * 
     * ### Placeholders:
     * 
     *  - `[#~old:alias#]`: Loads the value the specified alias held BEFORE the event was applied.
     *  - `[#~new:alias#]`: Loads the value the specified alias will hold AFTER the event has been applied.
     * 
     * @uxon-property check_on_update
     * * @uxon-type \exface\Core\CommonLogic\DataSheets\DataCheckWithOutputData[]
     * * @uxon-template [{"output_data_sheet":{"object_alias": "", "rows": [{"CRITICALITY":"0", "LABELS":"", "MESSAGE":"", "COLOR":"", "ICON":"sap-icon://message-warning"}]}, "operator": "AND", "conditions": [{"expression": "", "comparator": "", "value": ""}]}]
     * 
     * @param UxonObject $uxon
     * @return AbstractValidatingBehavior
     */
    public function setCheckOnUpdate(UxonObject $uxon) : AbstractValidatingBehavior
    {
        $this->setUxonForEventContext($uxon,self::CONTEXT_ON_UPDATE);
        return $this;
    }

    /**
     * Triggers BOTH when data is being CREATED and UPDATED.
     * 
     * ### Placeholders:
     * 
     * - `[#~new:alias#]`: Loads the value the specified alias will hold AFTER the event has been applied.
     * 
     * @uxon-property check_always
     * * @uxon-type \exface\Core\CommonLogic\DataSheets\DataCheckWithOutputData[]
     * * @uxon-template [{"output_data_sheet":{"object_alias": "", "rows": [{"CRITICALITY":"0", "LABELS":"", "MESSAGE":"", "COLOR":"", "ICON":"sap-icon://message-warning"}]}, "operator": "AND", "conditions": [{"expression": "", "comparator": "", "value": ""}]}]
     * 
     * @param UxonObject $uxon
     * @return AbstractValidatingBehavior
     */
    public function setCheckAlways(UxonObject $uxon) : AbstractValidatingBehavior
    {
        $this->setUxonForEventContext($uxon,self::CONTEXT_ON_ANY);
        return $this;
    }
}