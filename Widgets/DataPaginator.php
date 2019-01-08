<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\BooleanDataType;

/**
 * Shows a pagination-control (e.g. toolbar), displaying the the current position, number of pages, navigation controls, etc.
 * 
 * In most cases, the template will set up pagination settings most suitable for it's widget representation automatically.
 * However, you can customize the paginator using this widget as shown below:
 * 
 * ```
 *  {
 *      "page_size": 40,
 *      "page_sizes": [20, 40, 100, 200]
 *  }
 *  
 * ```
 * 
 * For large data sets, disabling the total row counter on the paginator may improve performance significantly.
 * However, in this case, there will be no way to determine, how many pages or rows there are in total - the user
 * will only be able to navigate to the next page, if there is one.
 * 
 * ```
 *  {
 *      "count_all_rows": false
 *  }
 *  
 * ```
 *
 * @author Andrej Kabachnik
 *        
 */
class DataPaginator extends AbstractWidget
{
    private $dataWidget = null;
    
    private $pageSize = null;
    
    private $pageSizes = [];
    
    private $useTotalCount = true;
    
    public function getDataWidget() : Data
    {
        return $this->getParent();
    }
    
    /**
     *
     * @return int|NULL
     */
    public function getPageSize(int $default = null) : ?int
    {
        return $this->pageSize === null ? $default : $this->pageSize;
    }
    
    /**
     * Sets the the number of rows to display on a single page.
     * 
     * @uxon-property page_size
     * @uxon-type number
     * 
     * @param int $value
     * @return DataPaginator
     */
    public function setPageSize(int $value) : DataPaginator
    {
        $this->pageSize = $value;
        return $this;
    }
    
    /**
     *
     * @return array
     */
    public function getPageSizes() : array
    {
        return $this->pageSizes;
    }
    
    /**
     * Sets page sizes, the user can pick from (some templates will allow to change the page size).
     * 
     * Set to an empty array to enforce the preset page size!
     * 
     * @uxon-property page_sizes
     * @uxon-type integer[]
     * @uxon-template [""]
     * 
     * @param int[] $value
     * @return DataPaginator
     */
    public function setPageSizes(array $value) : DataPaginator
    {
        $this->pageSizes = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        if ($this->getPageSize() !== null) {
            $uxon->setProperty('page_size', $this->getPageSize());
        }
        if (! empty($this->getPageSizes())) {
            $uxon->setProperty('page_sizes', $this->getPageSizes());
        }
        return $uxon;
    }
    
    /**
     *
     * @return bool
     */
    public function getCountAllRows() : bool
    {
        return $this->useTotalCount;
    }
    
    /**
     * Set to FALSE to improve performance by disabling the total row counter.
     * 
     * The downside of this option is that the paginator will not know, how many
     * pages there are in total, so the user will only be able to navigate to
     * the next or any of the previous pages - not to the last page.
     * 
     * @uxon-property count_all_rows
     * @uxon-type boolean
     * 
     * @param bool|string $value
     * @return DataPaginator
     */
    public function setCountAllRows($value) : DataPaginator
    {
        $this->useTotalCount = BooleanDataType::cast($value);
        return $this;
    }   
}