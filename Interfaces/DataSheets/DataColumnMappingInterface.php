<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\Model\ExpressionInterface;

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
     * @return ExpressionInterface
     */
    public function getFromExpression();
    
    /**
     *
     * @param ExpressionInterface $expression
     * @return DataColumnMappingInterface
     */
    public function setFromExpression(ExpressionInterface $expression);
    
    /**
     * @return ExpressionInterface
     */
    public function getToExpression();
    
    /**
     *
     * @param ExpressionInterface $expression
     * @return DataColumnMappingInterface
     */
    public function setToExpression(ExpressionInterface $expression);
}