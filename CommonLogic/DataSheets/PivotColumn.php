<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Interfaces\DataSheets\PivotColumnInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\PivotColumnGroupInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Exceptions\InvalidArgumentException;

/**
 * A data column resulting from transposed data
 * 
 * @author Andrej Kabachnik
 *
 */
class PivotColumn extends DataColumn implements PivotColumnInterface
{
    private $pivotColGroup = null;
    
    /**
     * 
     * @param string|ExpressionInterface $expression
     * @param DataSheetInterface $data_sheet
     * @param string $name
     * @param PivotColumnGroupInterface $pivotGroup
     */
    function __construct($expression, DataSheetInterface $data_sheet, $name = '', PivotColumnGroupInterface $pivotGroup = null)
    {
        parent::__construct($expression, $data_sheet, $name);
        $this->pivotColGroup = $pivotGroup;
        if ($pivotGroup === null) {
            throw new InvalidArgumentException('Cannot create pivot column: no column group provided!');
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\PivotColumnInterface::getPivotColumnGroup()
     */
    public function getPivotColumnGroup() : PivotColumnGroupInterface
    {
        return $this->pivotColGroup;
    }
}