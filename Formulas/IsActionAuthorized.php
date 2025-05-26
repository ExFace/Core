<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\CommonLogic\Security\Authorization\ActionAuthorizationPoint;
use exface\Core\Exceptions\FormulaError;
use exface\Core\Exceptions\Security\AccessPermissionDeniedError;
use exface\Core\Factories\ActionFactory;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Factories\TaskFactory;

/**
 * Returns TRUE if the current user is authorized to call the given action and FALSE otherwise
 * 
 * @author Andrej Kabachnik
 */
class IsActionAuthorized extends Formula
{
    private static array $actionsCache = [];

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run(?string $actionAliasOrUid = null, string $username = null): bool
    {
        if ($actionAliasOrUid === null) {
            throw new FormulaError("No action provided for =IsActionAuthorized() formula");
        }

        // Get the action
        $action = static::$actionsCache[$actionAliasOrUid] ?? null;
        if ($action === null) {
            $action = ActionFactory::createFromString($this->getWorkbench(), $actionAliasOrUid);
            static::$actionsCache[$actionAliasOrUid] = $action;
        }

        // Get the input data
        $allData = $this->getDataSheet();
        $row = $allData->getRow($this->getCurrentRowNumber());
        $inputData = $allData->copy()->removeRows()->addRow($row, false, false);
        // Create a fake task
        $task = TaskFactory::createFromDataSheet($inputData);

        // See if the action is authorized for this input data
        $actionAP = $this->getWorkbench()->getSecurity()->getAuthorizationPoint(ActionAuthorizationPoint::class);
        try {
            $actionAP->authorize($action, $task);
        } catch (AccessPermissionDeniedError $e) {
            return false;
        }
        return true;
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