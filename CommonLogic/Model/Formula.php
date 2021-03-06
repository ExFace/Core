<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Factories\DataTypeFactory;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Formulas\FormulaInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\Selectors\FormulaSelectorInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\FormulaError;

/**
 * Data functions are much like Excel functions.
 * They calculate
 * the value of a cell in a data_sheet based on other data from
 * this sheet and user defined arguments.
 *
 * @author Andrej Kabachnik
 *        
 */
abstract class Formula implements FormulaInterface
{

    private $required_attributes = array();

    private $arguments = array();

    private $data_sheet = null;

    private $relation_path = null;

    private $data_type = NULL;

    private $exface = null;
    
    private $selector = null;

    private $current_column_name = null;

    private $current_row_number = null;

    /**
     *
     * @deprecated use FormulaFactory instead!
     * @param Workbench $workbench            
     */
    public function __construct(FormulaSelectorInterface $selector)
    {
        $this->exface = $selector->getWorkbench();
        $this->selector = $selector;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaInterface::getSelector()
     */
    public function getSelector() : FormulaSelectorInterface
    {
        return $this->selector;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Formulas\FormulaInterface::init()
     */
    public function init(array $arguments)
    {
        // now find out, what each parameter is: a column reference, a string, a widget reference etc.
        foreach ($arguments as $arg) {
            $expr = $this->getWorkbench()->model()->parseExpression(trim($arg));
            $this->arguments[] = $expr;
            $this->required_attributes = array_merge($this->required_attributes, $expr->getRequiredAttributes());
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Formulas\FormulaInterface::evaluate()
     */
    public function evaluate(\exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet = null, int $row_number = null)
    {
        $args = array();
        
        try {
            if ($this->isStatic()) {
                foreach ($this->arguments as $expr) {
                    $args[] = $expr->evaluate();
                }
                
            } else {
                if (is_null($data_sheet) || is_null($row_number)) {
                    throw new InvalidArgumentException('In a non-static formula $data_sheet, $column_name and $row_number are mandatory arguments.');
                }
                
                
                foreach ($this->arguments as $expr) {
                    $args[] = $expr->evaluate($data_sheet, $row_number);
                }
                
                
                $this->setDataSheet($data_sheet);
                $this->setCurrentRowNumber($row_number);
            }
            
            return call_user_func_array(array(
                $this,
                'run'
            ), $args);
        } catch (\Throwable $e) {
            $errorText = 'Cannot evaluate formula `' . $this->__toString() . '`';
            if ($data_sheet === null) {
                $errorText .= ' statically!';
            } else {
                $onRow = $row_number !== null ? ' on row ' . $row_number : '';
                $errorText .= ' for data of ' . $data_sheet->getMetaObject()->getAliasWithNamespace() . $onRow . '!';
            }
            throw new FormulaError($errorText . ' ' . $e->getMessage(), null, $e);
        }
    }

    public function getRelationPath()
    {
        return $this->relation_path;
    }

    public function setRelationPath($relation_path)
    {
        // set new relation path
        $this->relation_path = $relation_path;
        if ($relation_path) {
            foreach ($this->arguments as $key => $a) {
                $a->setRelationPath($relation_path);
                $this->arguments[$key] = $a;
            }
        }
        return $this;
    }

    public function getRequiredAttributes()
    {
        return $this->required_attributes;
    }

    /**
     * 
     * @return ExpressionInterface[]
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * Returns the data sheet, the formula is being run on
     *
     * @return DataSheetInterface
     */
    public function getDataSheet()
    {
        return $this->data_sheet;
    }

    /**
     *
     * @param DataSheetInterface $value            
     * @return \exface\Core\CommonLogic\Model\Formula
     */
    protected function setDataSheet(DataSheetInterface $value)
    {
        $this->data_sheet = $value;
        return $this;
    }

    public function getDataType()
    {
        if (is_null($this->data_type)) {
            $this->data_type = DataTypeFactory::createBaseDataType($this->getWorkbench());
        }
        return $this->data_type;
    }

    public function setDataType($value)
    {
        $this->data_type = $value;
    }

    public function mapAttribute($map_from, $map_to)
    {
        foreach ($this->required_attributes as $id => $attr) {
            if ($attr == $map_from) {
                $this->required_attributes[$id] = $map_to;
            }
        }
        foreach ($this->arguments as $key => $a) {
            $a->mapAttribute($map_from, $map_to);
            $this->arguments[$key] = $a;
        }
    }

    /**
     * Returns the row number in the data sheet currently being processed.
     *
     * @return integer
     */
    public function getCurrentRowNumber()
    {
        return $this->current_row_number;
    }

    /**
     *
     * @param integer $value            
     * @return \exface\Core\CommonLogic\Model\Formula
     */
    protected function setCurrentRowNumber($value)
    {
        $this->current_row_number = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaInterface::isStatic()
     */
    public function isStatic() : bool
    {
        // A formula is static if it has no arguments or all arguments are static.
        // In other words, it is static if it does not have non-static arguments.
        foreach ($this->getArguments() as $expr) {
            if (! $expr->isStatic()) {
                return false;
            }
        }
        
        return true;
    }
    
    public function __toString()
    {
        return $this->getSelector()->toString() . '(' . implode(', ', $this->getArguments()) . ')';
    }
}
?>