<?php
namespace exface\Core\Widgets;

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
     * @uxon-type array
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
    
}
?>