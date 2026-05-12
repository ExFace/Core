<?php

namespace exface\Core\Calculations;

use exface\Core\Calculations\Prototypes\ForEachCalculation;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\WorkbenchInterface;

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

    private WorkbenchInterface $workbench;
    private ?string $name = null;
    private ?string $alias = null;
    private ?DataSheetInterface $subjectDataTemplate = null;
    private ?UxonObject $instructionsUxon = null;
    private ?array $instructions = [];
    private ?UxonObject $variableDefinitionsUxon = null;
    private ?array $variableDefinitions = [];

    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }
    
    public static function fromUxon(WorkbenchInterface $workbench, UxonObject $uxonObject) : static
    {
        if(!$uxonObject->hasProperty('alias')) {
            throw new InvalidArgumentException('Cannot instantiate calculation, because alias is missing!');
        }
        
        $alias = $uxonObject->getProperty('alias');
        $class = ForEachCalculation::class;
        
        $result = new $class($workbench);
        $result->importUxonObject($uxonObject);
        
        return $result;
    }

    /**
     * Performs the calculation using the provided input data.
     * 
     * @param DataSheetInterface $inputData
     * @return DataSheetInterface
     */
    public abstract function perform(DataSheetInterface $inputData) : DataSheetInterface;

    /**
     * Resolves all variable definitions and returns an array mapping variable names to
     * their resolved values.
     * 
     * @return array
     */
    protected function resolveVariableDefinitions() : array
    {
        // TODO Resolve per placeholders and per forEach, if necessary.
        // TODO Batch similar definitions.
        $result = [];
        
        foreach ($this->getVariableDefinitions() as $definition) {
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
     * @uxon-property alias
     * @uxon-type string
     * 
     * @param string|null $alias
     * @return AbstractCalculation
     */
    public function setAlias(?string $alias): AbstractCalculation
    {
        $this->alias = $alias;
        return$this;
    }

    public function getName(): ?string
    {
        return $this->name;
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

    public function getInstructions(MetaObjectInterface $forObject): array
    {
        if($this->instructions === null && $this->instructionsUxon !== null) {
            $this->instructions = [];
            foreach ($this->instructionsUxon as $uxon) {
                $this->instructions[] = CalculationInstruction::fromUxon($forObject, $uxon);
            }
        }
        
        return $this->instructions ?? [];
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
     * [
     *   {
     *      "output_attribute_alias": "net_amount",
     *      "expression": "=Calc(PRICE * QUANTITY)"
     *   }
     * ]
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
        $this->instructions = null;
        return $this;
    }

    public function getVariableDefinitions(): array
    {
        if($this->variableDefinitions === null && $this->variableDefinitionsUxon !== null) {
            $this->variableDefinitions = [];
            $workbench = $this->getWorkbench();
            foreach ($this->variableDefinitionsUxon as $definitionUxon) {
                $this->variableDefinitions[] = VariableDefinitions::fromUxon($workbench, $definitionUxon);
            }
        }
        
        return $this->variableDefinitions ?? [];
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
        $this->variableDefinitions = null;
        return $this;
    }
}