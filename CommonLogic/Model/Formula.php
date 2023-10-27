<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Factories\DataTypeFactory;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Formulas\FormulaInterface;
use exface\Core\Interfaces\Selectors\FormulaSelectorInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\FormulaError;
use exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;

/**
 * Data functions are much like Excel functions.
 * They calculate the value of a cell in a data_sheet based on other data from
 * this sheet and user defined arguments.
 *
 * @author Andrej Kabachnik
 *        
 */
abstract class Formula implements FormulaInterface
{

    private $requiredAttributeAliases = null;

    private $currentDataSheet = null;

    private $relationPathString = null;

    private $dataType = NULL;

    private $exface = null;
    
    private $selector = null;

    private $currentRowNumber = null;
    
    private $tokenStream = null;

    /**
     *
     * @deprecated use FormulaFactory instead!
     * @param Workbench $workbench            
     */
    public function __construct(FormulaSelectorInterface $selector, FormulaTokenStreamInterface $tokenStream = null, $relationPath = null)
    {
        $this->exface = $selector->getWorkbench();
        $this->selector = $selector;
        $this->tokenStream = $tokenStream;
        $this->relationPathString = $relationPath;
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
     * @see \exface\Core\Interfaces\Formulas\FormulaInterface::evaluate()
     */
    public function evaluate(DataSheetInterface $dataSheet = null, int $rowNumber = null)
    {
        try {
            if ($this->__toString() === '') {
                throw new FormulaError('Cannot evalute empty formula!');
            }
            
            if ($this->isStatic()) {
                $args = [];
            } else {
                if (is_null($dataSheet) || is_null($rowNumber)) {
                    throw new InvalidArgumentException('In a non-static formula $data_sheet and $row_number are mandatory arguments.');
                }
                
                $args = $dataSheet->getRow($rowNumber);
                if ($args === null && $dataSheet->hasColumTotals()) {
                    $totals_number = $rowNumber - $dataSheet->countRows();
                    $args = $dataSheet->getTotalsRow($totals_number, false);
                }
                if ($args === null) {
                    throw new InvalidArgumentException('Row number "' . $rowNumber . '" not found in data sheet!');
                }
            }
            $expressionLanguage = new SymfonyExpressionLanguage($this->getWorkbench(), $dataSheet, $rowNumber);
            $result = $expressionLanguage->evaluate($this, $args);
            
            return $result;
        } catch (\Throwable $e) {
            $errorText = 'Cannot evaluate formula `' . $this->__toString() . '`';
            if ($dataSheet === null) {
                $errorText .= ' statically!';
            } else {
                $onRow = $rowNumber !== null ? ' on row ' . $rowNumber : '';
                $errorText .= ' for data of ' . $dataSheet->getMetaObject()->getAliasWithNamespace() . $onRow . '!';
            }
            throw new FormulaError($errorText . ' ' . $e->getMessage(), null, $e);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaInterface::setDataContext()
     */
    public function setDataContext(DataSheetInterface $dataSheet = null, int $rowIdx = null) : FormulaInterface
    {
        $this->currentDataSheet = $dataSheet;
        $this->currentRowNumber = $rowIdx;
        return $this;        
    }
    
    /**
     * Runs the logic of the formula on the provided arguments.
     * 
     * Implement this method to create a real formula. Make sure, all arguments are optional to
     * avoid compile errors. Also keep in mind, that most formulas should be able to work with
     * empty values (null and empty strings), because these may always occur in data.
     * 
     * @return mixed
     */
    abstract public function run();

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaInterface::getRequiredAttributes()
     */
    public function getRequiredAttributes(bool $withRelationPath = true) : array
    {
        if ($this->requiredAttributeAliases === null) {
            $tStream = $this->getTokenStream();
            if ($tStream === null) {
                return [];
            }
            
            $attrs = $tStream->getAttributes();            
            $this->requiredAttributeAliases = $attrs;
        }
        if ($withRelationPath && $this->hasRelationPath()) {
            $attrs = $this->requiredAttributeAliases;
            $relPathStr = $this->getRelationPathString();
            foreach ($attrs as $i => $attr) {
                $attrs[$i] = RelationPath::relationPathAdd($relPathStr, $attr);
            }
            return $attrs;
        }
        return $this->requiredAttributeAliases;
    }

    /**
     * Returns the data sheet, the formula is being run on
     *
     * @return DataSheetInterface|NULL
     */
    protected function getDataSheet() : ?DataSheetInterface
    {
        return $this->currentDataSheet;
    }

    public function getDataType()
    {
        if (is_null($this->dataType)) {
            $this->dataType = DataTypeFactory::createBaseDataType($this->getWorkbench());
        }
        return $this->dataType;
    }

    /**
     * 
     * @param DataTypeInterface $value
     */
    public function setDataType($value)
    {
        $this->dataType = $value;
    }

    /**
     * Returns the row number in the data sheet currently being processed.
     *
     * @return int|NULL
     */
    protected function getCurrentRowNumber() : ?int
    {
        return $this->currentRowNumber;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaInterface::isStatic()
     */
    public function isStatic() : bool
    {        
        return empty($this->getRequiredAttributes()) ? true : false;
    }
    
    public function __toString() : string
    {
        return $this->getTokenStream() ? $this->getTokenStream()->__toString() : '';
    }
    
    /**
     * 
     * @return FormulaTokenStreamInterface|NULL
     */
    protected function getTokenStream() : ?FormulaTokenStreamInterface
    {
        return $this->tokenStream;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaInterface::getFormulaName()
     */
    public function getFormulaName() : string
    {
        $name = $this->getTokenStream() ? $this->getTokenStream()->getFormulaName() : null;
        if ($name === null) {
            throw new RuntimeException('Can not extract formula name from token stream. Either no token stream exists for this formula or expression includes no formula!');
        }
        return $name;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaInterface::getNestedFormulas()
     */
    public function getNestedFormulas() : array
    {
        return $this->getTokenStream() ? $this->getTokenStream()->getNestedFormulas() : [];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaInterface::hasRelationPath()
     */
    public function hasRelationPath() : bool
    {
        return $this->relationPathString !== null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaInterface::getRelationPathString()
     */
    public function getRelationPathString() : ?string
    {
        return $this->relationPathString;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaInterface::withRelationPath()
     */
    public function withRelationPath(string $relationPath) : FormulaInterface
    {
        $selfClass = static::class;
        return new $selfClass($this->getSelector(), $this->getTokenStream(), $relationPath);
    }
}