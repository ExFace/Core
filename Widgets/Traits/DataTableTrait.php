<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Interfaces\Widgets\iShowData;

/**
 * This trait contains typical options for tabular data widgets.
 *
 * @author Andrej Kabachnik
 *        
 */
trait DataTableTrait
{

    private $striped = true;

    private $nowrap = true;

    public function getNowrap()
    {
        return $this->nowrap;
    }

    /**
     * Set to FALSE to enable text wrapping in all columns.
     *
     * @uxon-property nowrap
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param boolean $value            
     * @return \exface\Core\Widgets\DataTable
     */
    public function setNowrap(bool $value) : iShowData
    {
        $this->nowrap = $value;
        return $this;
    }

    public function getStriped() : bool
    {
        return $this->striped;
    }

    /**
     * Set to TRUE to make the rows background color alternate.
     *
     * @uxon-property striped
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param boolean $value            
     * @return \exface\Core\Widgets\DataTable
     */
    public function setStriped(bool $value) : iShowData
    {
        $this->striped = $value;
        return $this;
    }
}