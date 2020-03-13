<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\IntegerDataType;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\Selectors\DataTypeSelectorInterface;
use exface\Core\Factories\SelectorFactory;

/**
 * Default implementation of the AggregatorInterface
 * 
 * IDEA Having the AggregatorFunctionsDataType now, we could transfer all the logic to the data type
 * and remove the AggregatorInterface. This would also allow to validate aggregators including
 * their options and would be cleaner in general.
 * 
 * @author Andrej Kabachnik
 *
 */
class Aggregator implements AggregatorInterface {
    
    private $aggregator_string = null;
    
    private $function = null;
    
    private $arguments = [];
    
    private $workbench = null;
    
    /**
     * 
     * @param Workbench $workbench
     * @param string|AggregatorFunctionsDataType $aggregator_string
     * @param string[] $arguments
     */
    public function __construct(Workbench $workbench, $aggregator_string, array $arguments = null)
    {
        $this->workbench = $workbench;
        $aggregator_string = (string) $aggregator_string;
        $this->aggregator_string = $aggregator_string;
        $this->importString($aggregator_string);
        if ($arguments === null) {
            $this->arguments = array_merge($arguments, $this->arguments);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\AggregatorInterface::getFunction()
     */
    public function getFunction()
    {
        return $this->function;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\AggregatorInterface::getArguments()
     */
    public function getArguments()
    {
        return $this->arguments;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\AggregatorInterface::hasArguments()
     */
    public function hasArguments()
    {
        return empty($this->arguments) ? false : true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToString::exportString()
     */
    public function exportString()
    {
        return $this->getFunction() . ($this->hasArguments() ? '(' . implode(', ', $this->getArguments()) . ')' : '');
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToString::importString()
     */
    public function importString($aggregator_string)
    {
        if ($args_pos = strpos($aggregator_string, '(')) {
            $this->function = new AggregatorFunctionsDataType($this->getDataTypeSelector(), strtoupper(substr($aggregator_string, 0, $args_pos)));
            $this->arguments = explode(',', substr($aggregator_string, ($args_pos + 1), - 1));
            $this->arguments = array_map('trim', $this->arguments);
        } else {
            $this->function = new AggregatorFunctionsDataType($this->getDataTypeSelector(), $aggregator_string);
        }
        return $this;
    }
    
    /**
     * 
     * @return DataTypeSelectorInterface
     */
    protected function getDataTypeSelector() : DataTypeSelectorInterface
    {
        return SelectorFactory::createDataTypeSelector($this->getWorkbench(), static::class);
    }
    
    /**
     * 
     * @return string
     */
    public function __toString()
    {
        return $this->exportString();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\AggregatorInterface::is()
     */
    public function is($stringOrAggregator) : bool
    {
        if ($stringOrAggregator instanceof AggregatorInterface) {
            $aggregator = $stringOrAggregator;
        } else {
            $aggregator = new self($this->getWorkbench(), $stringOrAggregator);
        }
        return $this->getFunction() == $aggregator->getFunction();
    }
    
    /**
     * 
     * @param DataTypeInterface $aggregatedType
     * @return DataTypeInterface
     */
    public function getResultDataType(DataTypeInterface $aggregatedType)
    {
        switch ($this->getFunction()->__toString()) {
            case AggregatorFunctionsDataType::SUM:
                if ($aggregatedType instanceof BooleanDataType) {
                    $type = DataTypeFactory::createFromPrototype($this->getWorkbench(), IntegerDataType::class);
                } else {
                    $type = $aggregatedType->copy();
                }
                break;
            case AggregatorFunctionsDataType::AVG:
                if ($aggregatedType instanceof NumberDataType) {
                    // If averaging numbers, we can keep the precision in most cases
                    $type = $aggregatedType->copy();
                    // However, if it is a whole number, it is a good idea to show at
                    // least one fraction digit as averages very often result in fractional
                    // values.
                    if (! $type->getPrecisionMin() && $type->getPrecisionMax() === 0) {
                        $type->setPrecisionMax(1);
                    }
                } else {
                    $type = DataTypeFactory::createFromPrototype($this->getWorkbench(), NumberDataType::class);
                }
                break;
            case AggregatorFunctionsDataType::COUNT:
            case AggregatorFunctionsDataType::COUNT_DISTINCT:
            case AggregatorFunctionsDataType::COUNT_IF:
                $type = DataTypeFactory::createFromPrototype($this->getWorkbench(), IntegerDataType::class);
                break;
            case AggregatorFunctionsDataType::MIN:
            case AggregatorFunctionsDataType::MAX:
                $type = $aggregatedType->copy();
                break;
            default:
                $type = DataTypeFactory::createBaseDataType($this->getWorkbench());
        }
        return $type;
    }
}