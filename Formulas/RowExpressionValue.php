<?php
namespace exface\Core\Formulas;

/**
 * Returns the value in the given row from the column with the given expression.
 * 
 * This can be useful if you need to check input data of an action if it contains the same data in all rows in a specific column.
 *
 * @author Ralf Mulansky
 *        
 */
class RowExpressionValue extends \exface\Core\CommonLogic\Model\Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run($expression = null, $rowNumber = null)
    {
        $dataSheet = $this->getDataSheet();
        if ($dataSheet !== null && $col = $dataSheet->getColumns()->getByExpression($expression)) {
            return $dataSheet->getCellValue($col->getName(), $rowNumber);
        }
        return null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::isStatic()
     */
    public function isStatic() : bool
    {
        return false;
    }
}