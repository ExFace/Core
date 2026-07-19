<?php

namespace exface\Core\Calculations;

use exface\Core\Calculations\Prototypes\ForEachCalculation;
use exface\Core\CommonLogic\Debugger\LogBooks\DataLogBook;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Templates\BracketHashStringTemplateRenderer;
use exface\Core\Templates\Placeholders\DataRowPlaceholders;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat\Wizard\Number;

/**
 * Calculations take a `DataSheet` as input and perform arbitrarily complex calculations, returning
 * the results as a new datasheet, based on their `subject_data` template.
 * 
 * They are configured via UXON and always have the following structure:
 * - `name`: A name for better readability in logs and other peripheries.
 * - `alias`: Determines the prototype to use, such as `ForEachCalculation`. The prototype controls
 * how data is read, transformed and written.
 * - `subject_data`: Defines a datasheet template that provides the actual data to be worked on. This may be
 * entirely unrelated to the input data.
 * - `instructions`: These contain the actual work instructions. They define an expression (e.g., a formula)
 * that will be applied to the subject data and an output alias that controls where results will be written to.
 * The expression may refer to variables defined in the next step.
 * - `variable_definitions`: Variable definitions contain any number of datasheets that load in additional data.
 * This data is then made available via variable mappings that assign an easily readable name to the data. These names
 * can be used to reference the data in the calculation instructions above.
 */
abstract class AbstractCalculation implements WorkbenchDependantInterface
{
    use ImportUxonObjectTrait;

    protected const TPL_JSON = 'json';
    protected const TPL_DEPENDENCIES = 'dependencies';
    protected const PHS_INPUT = '~input:';
    protected const CMP_VARIABLE_DEFINITIONS = 'variable definitions';
    protected const CMP_INSTRUCTIONS = 'instructions';
    
    private WorkbenchInterface $workbench;
    private ?string $name = null;
    private ?string $alias = null;
    private ?DataSheetInterface $subjectDataTemplate = null;
    private ?UxonObject $instructionsUxon = null;
    private ?UxonObject $variableDefinitionsUxon = null;
    protected array $renderingPlan = [];
    protected array $templates = [];
    protected ?LogBookInterface $logBook = null;
    private array $globalDefinitions = [];
    private array $globalInstructions = [];

    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }
    
    public static function fromUxon(WorkbenchInterface $workbench, UxonObject $uxonObject) : static
    {
        if(!$uxonObject->hasProperty('calculation_alias')) {
            throw new InvalidArgumentException('Cannot instantiate calculation, because alias is missing!');
        }
        
        // TODO Get class via alias.
        $alias = $uxonObject->getProperty('alias');
        $class = ForEachCalculation::class;
        
        $result = new $class($workbench);
        $result->importUxonObject($uxonObject);
        
        return $result;
    }
    
    protected abstract function init() : void;

    public final function perform(DataSheetInterface $inputData, LogBookInterface $logBook = null) : DataSheetInterface
    {
        $this->logBook = $logBook;
        $logBook?->addSection('Calculation: ' . $this->getName());
        
        $resultSheet = null;
        $workbench = $this->getWorkbench();
        $subjectObject = $this->getSubjectDataTemplate()?->getMetaObject() ?? $inputData->getMetaObject();
        $this->prepareCalculationComponents($subjectObject);
        $currentDependencies = [self::PHS_INPUT];

        $globalVariables = $this->resolveVariableDefinitions($this->globalDefinitions);
        $logBook?->addLine('Resolved global variables: ' . $this->printArray($globalVariables) . '.');


        if($inputData->countRows() === 0) {
            $logBook?->addLine('No input rows detected, cannot resolve input dependant variables and instructions.');
            $resultSheet = $this->performInternal($inputData, 0, $globalVariables, $this->globalInstructions, $currentDependencies);
        } else {
            $inputDependantDefinitions = $this->getTemplatesForDependencies(self::CMP_VARIABLE_DEFINITIONS, $currentDependencies);
            $inputDependantInstructions = $this->getTemplatesForDependencies(self::CMP_INSTRUCTIONS, $currentDependencies);
            
            $logBook?->addLine('Resolving ' . count($inputDependantDefinitions) . ' variable definitions and ' .
                count($inputDependantInstructions) . ' instructions for each of ' . $inputData->countRows() . ' input rows.');
            
            foreach ($inputData->getRows() as $rowNumber => $inputRow) {
                $renderer = new BracketHashStringTemplateRenderer($workbench);
                $renderer->addPlaceholder(new DataRowPlaceholders($inputData, $rowNumber, self::PHS_INPUT));

                $inputVariables = [];
                if(!empty($inputDependentDefinitions)) {
                    $inputVariables = $this->renderVariables($renderer, $inputDependentDefinitions);
                    $inputVariables = $this->resolveVariableDefinitions($inputVariables);
                    $logBook?->addLine('Resolved variables for input row #' . $rowNumber . ': ' . $this->printArray($inputVariables) . '.');
                }

                $inputInstructions = [];
                if(!empty($inputDependantInstructions)) {
                    $inputInstructions = $this->renderInstructions($renderer, $inputDependantInstructions, $subjectObject);
                    $logBook?->addLine('Resolved instructions for input row #' . $rowNumber . '.');
                }

                $outputSheet = $this->performInternal(
                    $inputData,
                    $rowNumber,
                    array_merge($globalVariables, $inputVariables),
                    array_merge($this->globalInstructions, $inputInstructions),
                    $currentDependencies
                );

                if($resultSheet === null) {
                    $resultSheet = $outputSheet;
                } else {
                    $resultSheet->addRows($outputSheet->getRows());
                }
            }
        }

        return $resultSheet;
    }

    /**
     * Performs the calculation using the provided input data.
     *
     * @param DataSheetInterface $inputData
     * @param int                $inputRow
     * @param array              $resolvedVariables
     * @param array              $resolvedInstructions
     * @param array              $currentDependencies
     * @return DataSheetInterface
     */
    protected abstract function performInternal(
        DataSheetInterface $inputData,
        int $inputRow,
        array $resolvedVariables,
        array $resolvedInstructions,
        array $currentDependencies
    ) : DataSheetInterface;
    
    protected function prepareCalculationComponents(MetaObjectInterface $subjectObject) : void
    {
        $workbench = $this->getWorkbench();

        $this->logBook?->addLine('Optimizing variable definitions...');
        $this->logBook?->addIndent(1);

        // TODO Allow [#~var:varName#] inside of definitions. This requires complex dependency resolvers and is non-trivial.

        $key = self::CMP_VARIABLE_DEFINITIONS;
        $this->prepareRenderingPlan($this->variableDefinitionsUxon, $key);

        $this->globalDefinitions = [];
        foreach ($this->getTemplatesForDependencies($key, []) as $template) {
            $this->globalDefinitions[] = VariableDefinitions::fromUxon($workbench, UxonObject::fromJson($template));
        }
        
        $this->logBook?->addLine('Completed rendering plan for ' . $key . ':' . $this->printArray($this->renderingPlan[$key]) . '.');
        $this->logBook?->addIndent(-1);
        $this->logBook?->addLine('Optimizing instructions...');
        $this->logBook?->addIndent(1);

        $key = self::CMP_INSTRUCTIONS;
        $this->prepareRenderingPlan($this->instructionsUxon, $key);

        $this->globalInstructions = [];
        foreach ($this->getTemplatesForDependencies($key, []) as $template) {
            $this->globalInstructions[] = CalculationInstruction::fromUxon($subjectObject, UxonObject::fromJson($template));
        }
        
        $this->logBook?->addLine('Completed rendering plan for ' . $key . ':' . $this->printArray($this->renderingPlan[$key]) . '.');
        $this->logBook?->addIndent(-1);
    }

    protected function printArray(array $array) : string
    {
        return json_encode($array, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    
    private function prepareRenderingPlan(UxonObject $uxonObject, string $key) : void
    {
        $this->renderingPlan[$key] = [];
        $this->templates[$key] = [];

        $idx = 0;
        $dependencies = $this->getDependencies();

        foreach ($uxonObject as $uxon) {
            $json = $uxon->toJson();

            $templateDependencies = [];
            $placeholders = StringDataType::findPlaceholders($json);
            foreach ($dependencies as $dependency) {
                foreach ($placeholders as $placeholder) {
                    if(str_starts_with($placeholder, $dependency)) {
                        $templateDependencies[] = $dependency;
                        continue 2;
                    }
                }
            }

            $this->templates[$key][$idx] = $json;
            $this->renderingPlan[$key][$idx] = $templateDependencies;
            
            $idx++;
        }
    }

    protected function getDependencies() : array
    {
        return [self::PHS_INPUT => self::PHS_INPUT];
    }
    
    protected function getTemplatesForDependencies(string $key, array $presentDependencies) : array
    {
        $result = [];
        $noDependencies = empty($presentDependencies);
        
        foreach ($this->renderingPlan[$key] as $idx => $expectedDependencies) {
            if($noDependencies !== empty($expectedDependencies)) {
                continue;
            }
            
            foreach ($expectedDependencies as $expectedDependency) {
                if(!in_array($expectedDependency, $presentDependencies)) {
                    continue 2;
                }
            }
            
            // If all expected dependencies are also present (or both are empty),
            // we have a match.
            $result[] = $this->templates[$key][$idx];
        }
        
        return $result;
    }
    
    protected function renderVariables(
        BracketHashStringTemplateRenderer $renderer, 
        array $templates
    ) : array
    {
        $renderedVariables = [];
        
        foreach ($templates as $template) {
            $renderedJson = $renderer->render($template);
            $renderedVariables[] = VariableDefinitions::fromUxon($this->getWorkbench(), UxonObject::fromJson($renderedJson));
        }
        
        return $renderedVariables;
    }

    protected function renderInstructions(
        BracketHashStringTemplateRenderer $renderer,
        array $templates,
        MetaObjectInterface $forObject
    ) : array
    {
        $renderedInstructions = [];

        foreach ($templates as $template) {
            $renderedJson = $renderer->render($template);
            $renderedInstructions[] = CalculationInstruction::fromUxon($forObject, UxonObject::fromJson($renderedJson));
        }

        return $renderedInstructions;
    }

    /**
     * Resolves all variable definitions in a given array and returns an array mapping variable names to
     * their resolved values.
     *
     * @param array $definitions
     * @return array
     */
    protected function resolveVariableDefinitions(array $definitions) : array
    {
        // TODO Resolve per placeholders and per forEach, if necessary.
        // TODO Batch similar definitions.
        $result = [];
        
        foreach ($definitions as $definition) {
            if(!$definition instanceof VariableDefinitions) {
                continue;
            }
            
            $sourceSheet = $definition->getSourceSheet()?->copy();
            if($sourceSheet === null || $sourceSheet->dataRead() === 0) {
                continue;
            }
            
            $row = $sourceSheet->getRow();
            foreach ($definition->getVariables() as $variable => $columnName) {
                $value = $row[$columnName];
                if($value !== null) {
                    $result[$variable] = $value;
                }
            }
        }
        
        return $result;
    }

    /**
     * @return DataSheetInterface|null
     */
    public function getSubjectDataTemplate(): ?DataSheetInterface
    {
        return $this->subjectDataTemplate;
    }

    /**
     * Define the `subject_data` for this calculation. 
     * 
     * This datasheet provides the data to perform the calculation on.
     * It can point to any object and is independent of the input datasheet.
     * If not configured, the input data will be used as subject data, instead.
     * 
     * ```
     * {
     *   "object_alias": "geb.testing.testing_geb",
     *   "columns": [
     *     {"attribute_alias": "TOTAL"},
     *     {"attribute_alias": "STATUS"}
     *   ],
     *   "filters": {
     *     "operator": "AND",
     *     "conditions": [
     *       {"expression": "STATUS", "comparator": "=", "value": "OPEN"}
     *     ]
     *   }
     * }
     * ```
     *
     * @uxon-property subject_data
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheet
     * @uxon-template {"object_alias":"","columns":[{"attribute_alias":""}],"filters":{"operator":"AND","conditions":[{"expression":"","comparator":"=","value":""}]}}
     * 
     * @param UxonObject $subjectDataUxon
     * @return $this
     */
    public function setSubjectData(UxonObject $subjectDataUxon): AbstractCalculation
    {
        $this->subjectDataTemplate = DataSheetFactory::createFromUxon($this->getWorkbench(), $subjectDataUxon);
        return $this;
    }

    public function getWorkbench() : WorkbenchInterface
    {
        return $this->workbench;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }

    /**
     * @uxon-property calculation_alias
     * @uxon-type string
     * 
     * @param string|null $alias
     * @return AbstractCalculation
     */
    public function setCalculationAlias(?string $alias): AbstractCalculation
    {
        $this->alias = $alias;
        return$this;
    }

    public function getName(): ?string
    {
        return $this->name ?? 'Untitled';
    }

    /**
     * @uxon-property name
     * @uxon-type string
     * 
     * @param string|null $name
     * @return $this
     */
    public function setName(?string $name): AbstractCalculation
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Define the actual work to be performed.
     * 
     * Each entry represents one calculation that will be performed on your `subject_data`. 
     * Its `expression` contains the formula or expression to evaluate, and it can use any
     * data present in your `subject_data` and ALL the variables from your `variable_definitions`.
     * The evaluation result is then written to the `output_attribute_alias` in your `subject_data`.
     * 
     * ```
     * 
     * [
     *   {
     *      "output_attribute_alias": "net_amount",
     *      "expression": "=Calc(PRICE * QUANTITY)"
     *   }
     * ]
     * 
     * ```
     * 
     * @uxon-property instructions
     * @uxon-type \exface\core\Calculations\CalculationInstruction[]
     * @uxon-template [{"output_attribute_alias": "", "expression": ""}]
     *
     * @param UxonObject $instructionsUxon
     * @return $this
     */
    public function setInstructions(UxonObject $instructionsUxon): AbstractCalculation
    {
        $this->instructionsUxon = $instructionsUxon;
        return $this;
    }
    
    /**
     * Define the `variable_definitions` for this calculation. 
     * 
     * Variables are used by `instructions` and allow you to condense data from various 
     * sources into easily readable tokens. Each definition loads a `source_sheet`. 
     * The values from its first row are assigned to variable names based on your mapping 
     * in `variables`. These variable names can then be referenced in your `instructions`.
     * 
     * NOTE: Variables MUST be scalars or aggregates. Make sure to either filter or aggregate your
     * source_sheet` properly.
     *
     * ```
     * [
     *   {
     *     "variables": {
     *       "taxRate": "TAX_RATE",
     *       "discount": "DISCOUNT_RATE"
     *     },
     *     "source_sheet": {
     *       "object_alias": "geb.testing.testing_geb",
     *       "columns": [
     *         {"attribute_alias": "TAX_RATE:MAX()"},
     *         {"attribute_alias": "DISCOUNT_RATE:MIN()"}
     *       ]
     *     }
     *   }
     * ]
     * ```
     *
     * @uxon-property variable_definitions
     * @uxon-type \exface\core\Calculations\VariableDefinitions[]
     * @uxon-template [{"variables":{"":""},"source_sheet":{"object_alias":"","columns":[{"attribute_alias":""}]}}]
     *
     * @param UxonObject $variableDefinitionsUxon
     * @return AbstractCalculation
     */
    public function setVariableDefinitions(UxonObject $variableDefinitionsUxon): AbstractCalculation
    {
        $this->variableDefinitionsUxon = $variableDefinitionsUxon;
        return $this;
    }
}