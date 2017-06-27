<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Formulas\FormulaInterface;

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

    private $current_column_name = null;

    private $current_row_number = null;

    /**
     *
     * @deprecated use FormulaFactory instead!
     * @param Workbench $workbench            
     */
    public function __construct(Workbench $workbench)
    {
        $this->exface = $workbench;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
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
            $expr = $this->getWorkbench()->model()->parseExpression($arg);
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
    public function evaluate(\exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet, $column_name, $row_number)
    {
        $args = array();
        foreach ($this->arguments as $expr) {
            $args[] = $expr->evaluate($data_sheet, $column_name, $row_number);
        }
        
        $this->setDataSheet($data_sheet);
        $this->setCurrentColumnName($column_name);
        $this->setCurrentRowNumber($row_number);
        
        return call_user_func_array(array(
            $this,
            'run'
        ), $args);
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
            $exface = $this->getDataSheet()->getWorkbench();
            $this->data_type = DataTypeFactory::createFromAlias($exface, EXF_DATA_TYPE_STRING);
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
     * Returns the column name of the data sheet column currently being processed
     *
     * @return string
     */
    public function getCurrentColumnName()
    {
        return $this->current_column_name;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaInterface::getCurrentColumn()
     */
    public function getCurrentColumn()
    {
        return $this->getDataSheet()->getColumns()->get($this->getCurrentColumnName());
    }

    /**
     *
     * @param string $value            
     * @return \exface\Core\CommonLogic\Model\Formula
     */
    protected function setCurrentColumnName($value)
    {
        $this->current_column_name = $value;
        return $this;
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
}
?>