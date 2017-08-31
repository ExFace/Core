<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\CommonLogic\Model\Expression;

/**
 * Maps one data sheet column to another column of another sheet.
 *
 * Columns are identified by expressions (e.g. attribute alias, formula, etc.).
 *
 * @author Andrej Kabachnik
 *
 */
interface DataColumnMappingInterface extends DataMappingInterface
{
    /**
     * @return Expression
     */
    public function getFromExpression();
    
    /**
     *
     * @param Expression $expression
     * @return DataColumnMappingInterface
     */
    public function setFromExpression(Expression $expression);
    
    /**
     * @return Expression
     */
    public function getToExpression();
    
    /**
     *
     * @param Expression $expression
     * @return DataColumnMappingInterface
     */
    public function setToExpression(Expression $expression);
}