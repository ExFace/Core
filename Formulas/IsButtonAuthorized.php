<?php

namespace exface\Core\Formulas;

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
            throw new FormulaError("Missing page alias or widget id for formula =IsButtonAuthorized()");
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
                throw new FormulaError("Invalid widget id for formula =IsButtonAuthorized() - the widget is not triggering an action!");
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

        $dataCache = static::$dataCache[$cacheKey] ?? null;
        if ($dataCache === null) {
            // See if the action has an input_mapper for the object of our data. If so, try to apply the mapper.
            // If the result has the same number of rows as the original data (should be in most simple cases),
            // we can cache the mapped data and avoid calling the mapper over and over again. This should save
            // us loading missing data, that is done automatically by mappers in most cases.
            if (null !== $mapper = $action->getInputMapper($allData->getMetaObject())) {
                $mappedData = $mapper->map($allData);
                // We can cache the mapped data if we only have a single row OR the number of rows did not
                // change after the mapping.
                $mapByRow = $allData->countRows() > 1 && $mappedData->countRows() !== $allData->countRows();
            } else {
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

        // Create fake input data for a single row
        $row = $mappedData->getRow($this->getCurrentRowNumber());
        $inputData = $mappedData->copy()->removeRows()->addRow($row, false, false);
        if ($mapByRow === true) {
            $inputData = $mapper->map($inputData);
        }

        // Create a fake task
        $task = TaskFactory::createFromDataSheet($inputData);

        // See if the action is authorized for this input data
        try {
            $actionAP->authorize($action, $task);
            $result = true;
        } catch (AccessPermissionDeniedError $e) {
            $this->getWorkbench()->getLogger()->logException($e, LoggerInterface::DEBUG);
            $result = false;
        }

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