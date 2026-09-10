<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;

/**
 * Shows a pagination-control (e.g. toolbar), displaying the the current position, number of pages, navigation controls, etc.
 * 
 * In most cases, the facade will set up pagination settings most suitable for it's widget representation automatically.
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
    const PAGE_BUTTON_PRIORITY_HIDDEN = 'hidden';
    const PAGE_BUTTON_PRIORITY_HIGH = 'high';
    const PAGE_BUTTON_PRIORITY_LOW = 'low';
    const PAGE_BUTTON_PRIORITY_ALWAYS_VISIBLE = 'always_visible';
    
    private $dataWidget = null;
    
    private $pageSize = null;
    
    private $pageSizes = null;
    
    private $useTotalCount = true;
    
    private $pageButtonPriority = null;
    
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
     * @return array|NULL
     */
    public function getPageSizes() : ?array
    {
        return $this->pageSizes;
    }
    
    /**
     * Available page sizes for the user to pick from (some facades will allow to select a page size in the table settings).
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
    public function setPageSizes($value) : DataPaginator
    {
        if ($value instanceof UxonObject) {
            $sizes = $value->toArray();
        } elseif (is_array($value)) {
            $sizes = $value;
        } else {
            $sizes = UxonObject::fromAnything($value)->toArray();
        }
        $this->pageSizes = $sizes;
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
        if ($this->getPageButtonPriority() !== null) {
            $uxon->setProperty('page_button_priority', $this->getPageButtonPriority());
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
    
    /**
     *
     * @return string|NULL
     */
    public function getPageButtonPriority() : ?string
    {
        return $this->pageButtonPriority;
    }
    
    /**
     * Explicitly control how the previous/next page buttons behave if the surrounding toolbar runs out of space.
     * 
     * By default, facades place the paginator inside a toolbar together with other buttons and controls
     * (e.g. the caption, filters, action buttons, etc.). If that toolbar does not have enough space - e.g.
     * because the widget is placed in a narrow tab or split panel - some of its contents are moved into an
     * overflow menu (the "..." button) to save space. This property lets you control that behavior explicitly
     * for the page buttons:
     * 
     * - `low` - moved into the overflow menu first (default)
     * - `high` - moved into the overflow menu only after all `low` priority items
     * - `always_visible` - never moved into the overflow menu - always directly accessible
     * - `hidden` - simply disappears if there is not enough space (not even available via the overflow menu)
     * 
     * @uxon-property page_button_priority
     * @uxon-type [hidden,high,low,always_visible]
     * 
     * @param string $value
     * @return DataPaginator
     */
    public function setPageButtonPriority(string $value) : DataPaginator
    {
        $value = mb_strtolower($value);
        $refl = new \ReflectionClass($this);
        if (! in_array($value, $refl->getConstants())) {
            throw new WidgetPropertyInvalidValueError($this, 'Invalid value "' . $value . '" for property `page_button_priority` of `paginator`: expecting `hidden`, `high`, `low` or `always_visible`!');
        }
        $this->pageButtonPriority = $value;
        return $this;
    }
}