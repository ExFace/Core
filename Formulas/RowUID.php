<?php
namespace exface\Core\Formulas;

/**
 * Returns the UID current row
 *
 * @author Andrej Kabachnik
 *        
 */
class RowUID extends \exface\Core\CommonLogic\Model\Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run()
    {
        $dataSheet = $this->getDataSheet();
        $idx = $this->getCurrentRowNumber();
        if ($dataSheet !== null && $dataSheet->hasUidColumn()) {
            return $dataSheet->getUidColumn()->getValue($idx);
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