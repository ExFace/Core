<?php

namespace exface\Core\Calculations\Prototypes;

use exface\Core\Calculations\AbstractCalculation;
use exface\Core\Calculations\CalculationInstruction;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * Applies calculation instructions row-by-row on a subject data sheet.
 *
 * These `instructions` are expressions (usually a formula) that are performed on each row of your `subject_data`.
 * They can use variables that you provide via `variable_definitions`.
 * 
 * ### Example
 * 
 * Sum the values of two source sheets and write the result to `value_a` for each subject row.
 * Note how the variables can draw data from any source.
 * 
 * ```
 * {
 *   "name": "TEST",
 *   "alias": "exface.Core.ForEachCalculation",
 *   "instructions": [
 *     {
 *       "output_attribute_alias": "value_a",
 *       "expression": "=Calc(countA + countB)"
 *     }
 *   ],
 *   "variable_definitions": [
 *     {
 *       "variables": {
 *         "countA": "value_a"
 *       },
 *       "source_sheet": {
 *         "object_alias": "geb.testing.testing_geb",
 *         "columns": [
 *           {
 *             "attribute_alias": "value_a:SUM()",
 *             "name": "value_a"
 *           }
 *         ]
 *       }
 *     },
 *     {
 *       "variables": {
 *         "countB": "value_b"
 *       },
 *       "source_sheet": {
 *         "object_alias": "geb.testing.testing_geb_bu",
 *         "columns": [
 *           {
 *             "attribute_alias": "value_b:SUM()",
 *             "name": "value_b"
 *           }
 *         ]
 *       }
 *     }
 *   ],
 *   "subject_data": {
 *     "object_alias": "geb.testing.testing_geb",
 *     "columns": [
 *       {
 *         "attribute_alias": "UID"
 *       },
 *       {
 *         "attribute_alias": "fake_user"
 *       }
 *     ],
 *     "filters": {
 *       "operator": "AND",
 *       "conditions": [
 *         {
 *           "expression": "fake_user",
 *           "comparator": "!==",
 *           "value": ""
 *         }
 *       ]
 *     }
 *   }
 * }
 * ```
 */
class ForEachCalculation extends AbstractCalculation
{
    /**
     * @inheritdocs 
     */
    public function perform(DataSheetInterface $inputData): DataSheetInterface
    {
        $subjectTemplate = $this->getSubjectDataTemplate();
        $subjectSheet = $subjectTemplate !== null ? $subjectTemplate->copy() : $inputData->copy();

        if ($subjectTemplate !== null) {
            $subjectSheet->dataRead();
        }

        $calcSheet = $subjectSheet->copy();
        foreach ($this->resolveVariableDefinitions() as $variable => $value) {
            $calcSheet->setColumnValues($variable, $value);
        }

        $forObject = $subjectSheet->getMetaObject();
        foreach ($subjectSheet->getRowIndexes() as $rowNumber) {
            foreach ($this->getInstructions($forObject) as $instruction) {
                if (! $instruction instanceof CalculationInstruction) {
                    continue;
                }

                $outputAlias = $instruction->getOutputAttributeAlias();
                if ($outputAlias === null || $outputAlias === '') {
                    continue;
                }
                
                $subjectSheet->setCellValue(
                    $outputAlias,
                    $rowNumber,
                    $instruction->getExpression()->evaluate($calcSheet, $rowNumber)
                );
            }
        }

        return $subjectSheet;
    }
}