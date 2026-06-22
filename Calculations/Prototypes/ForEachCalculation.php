<?php

namespace exface\Core\Calculations\Prototypes;

use exface\Core\Calculations\AbstractCalculation;
use exface\Core\Calculations\CalculationInstruction;
use exface\Core\Calculations\VariableDefinitions;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Templates\BracketHashStringTemplateRenderer;
use exface\Core\Templates\Placeholders\DataRowPlaceholders;

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
    protected const PH_FOREACH = '~foreach:';
    protected ?array $pureForEachInstructions = null;
    
    protected function init() : void
    {
        $this->pureForEachInstructions = null;
    }

    /**
     * @inheritdocs 
     */
    public function performInternal(
        DataSheetInterface $inputData,
        int $inputRow,
        array $resolvedVariables,
        array $resolvedInstructions,
        array $currentDependencies
    ): DataSheetInterface
    {
        $subjectTemplate = $this->getSubjectDataTemplate();
        $subjectSheet = $subjectTemplate !== null ? $subjectTemplate->copy() : $inputData->copy();
        $subjectObject = $subjectSheet->getMetaObject();
        $workbench = $this->getWorkbench();

        if ($subjectTemplate !== null) {
            $subjectSheet->dataRead();
        }

        $this->logBook?->addLine('Performing `ForEach` on input row #' . $inputRow . ' for ' . $subjectSheet->countRows() . ' subject rows.');
        $this->logBook?->addIndent(1);
        
        $calcSheet = $subjectSheet->copy();
        foreach ($resolvedVariables as $variable => $value) {
            $calcSheet->setColumnValues($variable, $value);
        }
        
        // We only need to evaluate pure ~foreach placeholders once per subject row.
        $dependencies = [self::PH_FOREACH];
        if($this->pureForEachInstructions === null) {
            $this->logBook?->addLine('Rendering components that only depend on "'. self::PH_FOREACH . '"...');
            $this->logBook?->addIndent(1);
            $this->pureForEachInstructions = [];

            $forEachVariables = $this->getTemplatesForDependencies(self::CMP_VARIABLE_DEFINITIONS, $dependencies);
            $forEachInstructions = $this->getTemplatesForDependencies(self::CMP_INSTRUCTIONS, $dependencies);
            foreach ($subjectSheet->getRowIndexes() as $rowNumber) {
                $renderer = new BracketHashStringTemplateRenderer($workbench);
                $renderer->addPlaceholder(new DataRowPlaceholders($subjectSheet, $rowNumber, self::PH_FOREACH));

                if(!empty($forEachVariables)) {
                    $variables = $this->renderVariables($renderer, $forEachVariables);
                    $variables = $this->resolveVariableDefinitions($variables);

                    $this->logBook?->addLine('Resolved ' . self::CMP_VARIABLE_DEFINITIONS . ' for subject row #' . $rowNumber . ': ' . $this->printArray($variables) . '.');

                    foreach ($variables as $variable => $value) {
                        $calcSheet->setColumnValues($variable, $value);
                    }
                }

                if(!empty($forEachInstructions)) {
                    $this->pureForEachInstructions[$rowNumber] = $this->renderInstructions($renderer, $forEachInstructions, $subjectObject);
                    $this->logBook?->addLine('Resolved ' . self::CMP_INSTRUCTIONS . ' for subject row #' . $rowNumber . '.');
                }
            }

            $this->logBook?->addIndent(-1);
        }

        $currentDependencies = array_merge($currentDependencies, $dependencies);
        $forEachVariables = $this->getTemplatesForDependencies(self::CMP_VARIABLE_DEFINITIONS, $currentDependencies);
        $forEachInstructions = $this->getTemplatesForDependencies(self::CMP_INSTRUCTIONS, $currentDependencies);
        foreach ($subjectSheet->getRowIndexes() as $rowNumber) {
            $renderer = new BracketHashStringTemplateRenderer($workbench);
            $renderer->addPlaceholder(new DataRowPlaceholders($inputData, $inputRow, self::PHS_INPUT));
            $renderer->addPlaceholder(new DataRowPlaceholders($subjectSheet, $rowNumber, self::PH_FOREACH));
            
            if(!empty($forEachVariables)) {
                $variables = $this->renderVariables($renderer, $forEachVariables);
                $variables = $this->resolveVariableDefinitions($variables);

                $this->logBook?->addLine('Resolved ' . self::CMP_VARIABLE_DEFINITIONS . ' for subject row #' . $rowNumber . ': ' . $this->printArray($variables) . '.');

                foreach ($variables as $variable => $value) {
                    $calcSheet->setColumnValues($variable, $value);
                }
            }

            $instructions = [];
            if(!empty($forEachInstructions)) {
                $instructions = $this->renderInstructions($renderer, $forEachInstructions, $subjectObject);
                $this->logBook?->addLine('Resolved ' . self::CMP_INSTRUCTIONS . ' for subject row #' . $rowNumber . '.');
            }
            
            $instructions = array_merge($resolvedInstructions, $instructions, $this->pureForEachInstructions[$rowNumber] ?? []);
            foreach ($instructions as $instruction) {
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
        
        $this->logBook?->addIndent(-1);
        return $subjectSheet;
    }
    
    protected function getDependencies() : array
    {
        $deps = parent::getDependencies();
        $deps[self::PH_FOREACH] = self::PH_FOREACH;
        return $deps;
    }
}