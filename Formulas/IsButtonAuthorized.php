<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\DataSheets\DataReadPrefetcher;
use exface\Core\CommonLogic\DataSheets\Mappings\DataCheckMapping;
use exface\Core\CommonLogic\Model\Formula;
use exface\Core\CommonLogic\Security\Authorization\ActionAuthorizationPoint;
use exface\Core\Exceptions\FormulaError;
use exface\Core\Exceptions\Security\AccessPermissionDeniedError;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Factories\TaskFactory;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Interfaces\Widgets\iTriggerAction;

/**
 * Returns TRUE if the current user is authorized to call the action of the given button and FALSE otherwise
 *
 * The main use case for this formula is having it generated automatically by a buttons hidden_if_access_denied.
 *
 * ## Possible recursion
 *
 * To avoid recursion, this formula MUST disable authorization logic while applying input mappers because those
 * might call this formula again! The formula remembers the data it is called for and returns NULL for any
 * intermediate calls with the same data
 *
 * If the formula is generated automatically by hidden_if_access_denied, it will be added to the input data of
 * the button as a hidden field. When the button is pressed, it will evaluate the actions input data, which in
 * turn might lead to recalculation of formulas - including this formula itself. The formula will find the
 * mapper and attempt to appy it, triggering the same logic once again and ultimately triggering itself.
 * 
 * @author Andrej Kabachnik
 */
class IsButtonAuthorized extends Formula
{
    private static array $actionsCache = [];
    private static array $dataCache = [];

    private $inProgressFor = [];

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run(string $pageAlias = '', string $widgetId = '') : ?bool
    {
        if ($pageAlias === '' || $widgetId === '') {
            throw new FormulaError($this, "Missing page alias or widget id for formula =IsButtonAuthorized()");
        }

        $actionAP = $this->getWorkbench()->getSecurity()->getAuthorizationPoint(ActionAuthorizationPoint::class);
        if ($actionAP->isDisabled() === true) {
            return true;
        }

        // Get the action
        $cacheKey = $pageAlias . ':' . $widgetId;
        $action = static::$actionsCache[$cacheKey] ?? null;
        if ($action === null) {
            // Get the Widget
            $page = UiPageFactory::createFromModel($this->getWorkbench(), $pageAlias);
            $widget = $page->getWidget($widgetId);
            if (! $widget instanceof iTriggerAction) {
                throw new FormulaError($this, "Invalid widget id for formula =IsButtonAuthorized() - the widget is not triggering an action!");
            }
            // Get the action from the widget
            $action = $widget->getAction();
            static::$actionsCache[$cacheKey] = $action;
        }
        
        // Get the input data
        $allData = $this->getDataSheet();
        $cacheKey = $cacheKey . ':' . spl_object_id($allData);

        // Avoid recursion while preparing data!
        if (true === $this->inProgressFor[$cacheKey] ?? false) {
            return null;
        }
        $this->inProgressFor[$cacheKey] = true;

        // Determine the data we will use for authorization. If the action has an input_mapper, we need to apply this
        // mapper to the input data and check action permissions relative to that $mappedData. If there are no mappers,
        // we can use the original input directly - but it is still called $mappedData for consistency.
        // Since applying mappings can be expensive, we cache $mappedData if possible. The static cache will allow
        // other instances of this formula to reuse the result of mappings without re-applying them.
        $dataCache = static::$dataCache[$cacheKey] ?? null;
        if ($dataCache === null) {
            // See if the action has an input_mapper for the object of our data. If so, try to apply the mapper.
            // If the result has the same number of rows as the original data (should be in most simple cases),
            // we can cache the mapped data and avoid calling the mapper over and over again. This should save
            // us loading missing data, that is done automatically by mappers in most cases.
            if (null !== $mapper = $action->getInputMapper($allData->getMetaObject())) {
                foreach ($mapper->getMappings() as $mapping) {
                    if ($mapping instanceof DataCheckMapping) {
                        $mapping->disable(true);
                    }
                }
                  
                $mappedData = $mapper->map($allData);
                // We can cache the mapped data if we only have a single row OR the number of rows did not
                // change after the mapping. Note: the number of rows can change not only when using pivot-type
                // mappings, but also when `invalid_if` mappings remove invalid rows. 
                $mapByRow = $allData->countRows() > 1 && $mappedData->countRows() !== $allData->countRows();
                if ($mapByRow === true) {
                    $mappedData = null;
                }
            } else {
                // If there is no mapper to apply, we can cache the original input data and use it for all rows
                $mappedData = $allData;
                $mapByRow = false;
            }
            static::$dataCache[$cacheKey] = [
                'mappedData' => $mapByRow ? null : $mappedData,
                'mapByRow' => $mapByRow,
                'mapper' => $mapper
            ];
        } else {
            $mappedData = $dataCache['mappedData'] ?? $allData;
            $mapByRow = $dataCache['mapByRow'] ?? false;
            $mapper = $dataCache['mapper'] ?? null;
        }
        // At this point:
        // - `$mapByRow` - shows if we need to apply the mapper for every row separately (true) or if we can use the
        // cached `$mappedData` for all rows (false)
        // - $mapper - the mapper instance applicable to the input data
        // - $mappedData - the result of the mapping.
        
        // Create fake input data to test if the action is authorized for the row we are currently calculating.
        // Basically, we simulate, what would happen if the user actually selected this row and pressed the button. 
        // - If we were able to apply the mapper and cache the result, we can use this result here. The action will not
        // apply its mappers anymore, and we will see, if it is allowed for the result of the mapper or not.
        // - If we could NOT cache mapper results, we take each row of the original data the formula is called on and
        // pass that to the action. The action will then apply its mappers regularly and - again - we will see,
        // if the action is allowed for the result of the mapper or not.
        // TODO having $mapByRow AND $mappedData both makes the code very hard to read - improve readability!
        // We could leave $mappedData only and set it to NULL if we need to map by row. Or we could rename vairables in
        // a way, that each DataSheet represents a processing stage: $formulaData > $mappedData > $inputData and use
        // $mapByRow to explicitly select one of them.
        $dataToCheck = $mapByRow ? $allData : ($mappedData ?? $allData);
        $rowToCheck = $dataToCheck->getRow($this->getCurrentRowNumber());
        $fakeInputData = $dataToCheck->copy()->removeRows();
        $fakeInputData->getSorters()->removeAll();
        $fakeInputData->addRow($rowToCheck, false, false);

        // TODO do we need to map data here? Wouldn't the action authorization point map it too???
        if ($mapByRow === true) {
            $fakeInputData = $mapper->map($fakeInputData);
        }

        // Create a fake task
        $task = TaskFactory::createFromDataSheet($fakeInputData);
        
        // Listen to data reads on the object of the data sheet we are filling and remember read column, that were
        // not there yet. The observer will add these column to the $mappedData and fill them for ALL rows when they
        // are read for the first time - this will prevent these column from bein read separately for every row this
        // formula is calculating
        /* TODO test #DataReadPrefetcher here
        $observer = new DataReadPrefetcher($mappedData->getMetaObject(), $mappedData);
        $observer->setPrefetchOnEveryRead(true);
        $observer->addDataSheetToEnrich($fakeInputData);
        $observer->start();
        */

        // See if the action is authorized for this input data
        try {
            $actionAP->authorize($action, $task);
            $result = true;
            /* TODO pass ALL rows to the authorization point at once instead of the current row only
             * This should be much faster as any additional data required for policies will only need to be loaded once
             * and not once per row! A prefetcher does not always help here, because input mapper eventually evaluated
             * by the policies themselves still are applied per-row as the policy does not know, that originally there
             * were multiple rows.
             *//*
            $task = TaskFactory::createFromDataSheet($allData);
            $decisions = $actionAP->authorizePerRow($action, $task);
            $result = $decisions[$this->getCurrentRowNumber()]->getPermitted();
            */
        } catch (AccessPermissionDeniedError $e) {
            $this->getWorkbench()->getLogger()->logException($e, LoggerInterface::DEBUG);
            $result = false;
        }

        /* TODO test #DataReadPrefetcher here
        $observer->stop();
        */
        // Stop blocking recursion
        unset($this->inProgressFor[$cacheKey]);

        return $result;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), BooleanDataType::class);
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::isStatic()
     */
    public function isStatic() : bool
    {
        return false;
    }
}