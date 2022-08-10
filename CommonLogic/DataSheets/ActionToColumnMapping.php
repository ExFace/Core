<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Factories\ActionFactory;
use exface\Core\Interfaces\Actions\iModifyData;
use exface\Core\Factories\TaskFactory;
use exface\Core\Interfaces\Tasks\ResultFileInterface;
use exface\Core\Interfaces\Tasks\ResultDataInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\DataSheets\DataMappingFailedError;

/**
 * Performs an action on the from-sheet an puts its results into the to-sheet
 * 
 * This mapper is usefull to perform some complex tasks to fill a column in the data sheet.
 * 
 * ## Examples
 * 
 * Save the result of a printing action like `PrintTemplate` to a data column:
 * 
 * ```
 *  {
 *      "action_to_column_mappings": [
 *          {
 *              "from": "~file:contents",
 *              "to": "print_content_attribute",
 *              "action": {
 *                  "alias":"my.App.PrintSomething"
 *              }
 *          },
 *          {
 *              "from": "~file:mimetype",
 *              "to": "print_filetype_attribute",
 *              "action": {
 *                  "alias":"my.App.PrintSomething"
 *              }
 *          }
 *      ]
 *  }
 *  
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class ActionToColumnMapping extends AbstractDataSheetMapping 
{
    private $actionUxon = null;
    
    private $action = null;
    
    private $from = null;
    
    private $to = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet)
    {
        $action = $this->getAction();
        
        if ($action->isTriggerWidgetRequired()) {
            throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Cannot use actions that require a trigger widget in data mappers!');
        }
        // IDEA allow modifying actions too if we can get the transaction here somehow...
        if ($action instanceof iModifyData) {
            throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Cannot use modifying actions in data mappers!');
        }
        
        $task = TaskFactory::createFromDataSheet($fromSheet);
        try {
            $result = $action->handle($task);
        } catch (\Throwable $e) {
            throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Error in data mapper: ' . $e->getMessage(), null, $e);
        }
        
        $from = $this->getFrom();
        $fromValues = [];
        switch (true) {
            case StringDataType::startsWith($from, '~file', false):
                if (! $result instanceof ResultFileInterface) {
                    throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Cannot use `~file` as from-expression in an action mapper if the result of the mappers action is not a file!');
                }
                switch (strtolower($from)) {
                    case '~file':
                    case '~file:contents':
                        $fromValues = [$result->getContents()];
                        break;
                    case '~file:mimetype':
                        $fromValues = [$result->getMimeType()];
                        break;
                }
                break;
            case ($result instanceof ResultDataInterface) && $fromCol = $result->getData()->getColumns()->get($from):
                $fromValues = $fromCol->getValues();
                break;
            default:
                throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Cannot use "' . $from . '" as from-expression in a action-to-column mapping: expeciton `~file` or a name of the actions result data column!');
        }
        
        $toCol = $toSheet->getColumns()->addFromExpression($this->getTo());
        if (count($fromValues) === 1) {
            $toCol->setValueOnAllRows($fromValues[0]);
        } else {
            $toCol->setValues($fromValues);
        }
        
        return $toSheet;
    }
    
    /**
     * 
     * @return ActionInterface
     */
    protected function getAction() : ActionInterface
    {
        if ($this->action === null && $this->actionUxon !== null) {
            $this->action = ActionFactory::createFromUxon($this->getWorkbench(), $this->actionUxon);
        }
        return $this->action;
    }
    
    /**
     * This action will be performed on the from-sheet.
     * 
     * @uxon-property action
     * @uxon-type \exface\Core\CommonLogic\AbstractAction
     * @uxon-template {"alias": ""}
     * 
     * @param UxonObject $value
     * @return ActionToColumnMapping
     */
    protected function setAction(UxonObject $value) : ActionToColumnMapping
    {
        $this->action = null;
        $this->actionUxon = $value;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getFrom() : string
    {
        return $this->from;
    }
    
    /**
     * Column or other property of the actions result to put into the to-expression
     *
     * Possible values:
     * 
     * - Name of a column in the result data sheet of the action
     * - `~file` for the file contents in case the action produces a downloadable file
     *
     * @uxon-property from
     * @uxon-type metamodel:attribute|'~file'
     * @uxon-required true
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::setFrom()
     */
    protected function setFrom(string $columnInActionResult) : ActionToColumnMapping
    {
        $this->from = $columnInActionResult;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getTo() : string
    {
        return $this->to;
    }
    
    /**
     * Name of the column in the to-sheet where the from-expression value is to be put
     *
     * @uxon-property to
     * @uxon-type metamodel:attribute
     * @uxon-required true
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::setTo()
     */
    protected function setTo(string $columnInToSheet) : ActionToColumnMapping
    {
        $this->to = $columnInToSheet;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::getRequiredExpressions()
     */
    public function getRequiredExpressions(DataSheetInterface $fromSheet) : array
    {
        return [];
    }
}