<?php

namespace exface\Core\Calculations;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Defines a set of variables that can be used in other contexts. 
 * 
 * Variables allow you to condense data from various sources into easily readable tokens. 
 * Data is loaded from the `source_sheet`. The values in its first row are assigned to variable names
 * based on your mapping in `variables`.
 *  
 * NOTE: Variables MUST be scalars or aggregates. Make sure to either filter or aggregate your
 * source_sheet` properly.
 * 
 *  ```
 *  [
 *    {
 *      "variables": {
 *        "taxRate": "TAX_RATE",
 *        "discount": "DISCOUNT_RATE"
 *      },
 *      "source_sheet": {
 *        "object_alias": "geb.testing.testing_geb",
 *        "columns": [
 *          {"attribute_alias": "TAX_RATE:MAX()"},
 *          {"attribute_alias": "DISCOUNT_RATE:MIN()"}
 *        ]
 *      }
 *    }
 *  ]
 *  ```
 */
class VariableDefinitions implements WorkbenchDependantInterface
{
    use ImportUxonObjectTrait;

    private WorkbenchInterface $workbench;
    private ?UxonObject $sourceSheetUxon = null;
    private ?DataSheetInterface $sourceSheet = null;
    private array $variables = [];

    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }
    
    public static function fromUxon(WorkbenchInterface $workbench, UxonObject $uxonObject) : VariableDefinitions
    {
        $result = new VariableDefinitions($workbench);
        $result->importUxonObject($uxonObject);
        return $result;
    }

    public function getWorkbench(): WorkbenchInterface
    {
        return $this->workbench;
    }

    /**
     * Returns the data sheet that loads the source data for the variables.
     *
     * @return DataSheetInterface|null
     */
    public function getSourceSheet(): ?DataSheetInterface
    {
        return $this->sourceSheet;
    }

    /**
     * A datasheet that loads in the source data for the variables.
     * Its columns should resolve to scalars or aggregates.
     * 
     * @uxon-property source_sheet
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheet
     * @uxon-template {"object_alias":"","columns":[{"attribute_alias":""}]}
     * 
     * @param UxonObject $sourceSheetUxon
     * @return $this
     */
    public function setSourceSheet(UxonObject $sourceSheetUxon): VariableDefinitions
    {
        $this->sourceSheetUxon = $sourceSheetUxon;
        $this->sourceSheet = DataSheetFactory::createFromUxon($this->getWorkbench(), $sourceSheetUxon);
        return $this;
    }

    /**
     * Returns the mapping of column names to variable names.
     * 
     * @return array
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * Maps column names to variable names.
     * The key is the variable name and the value is the column name. Therefore, variable
     * names must be unique.
     *
     * ```
     * 
     * {
     *     "variable_name_1": "COLUMN_NAME_1",
     *     "variable_name_2": "COLUMN_NAME_2"
     * }
     * 
     * ```
     *
     * @uxon-property variables
     * @uxon-type array
     * @uxon-template {"":""}
     *
     * @param UxonObject $variableMapping
     * @return $this
     */
    public function setVariables(UxonObject $variableMapping): VariableDefinitions
    {
        $this->variables = $variableMapping->toArray();
        return $this;
    }
}