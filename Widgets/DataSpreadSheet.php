<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Widgets\Traits\EditableTableTrait;

/**
 * An Excel-like table with editable cells.
 * 
 * THe spreadsheet is very handy for editing multiple rows of data. Depending on the facade used,
 * it will have Excel-like features like autofill, formulas, etc.
 * 
 *  An editor widget can be defined for every column. If no editor is explicitly defined, the default
 * editor for the attribute will be used - similarly to a DataTable with editable columns.
 * 
 * In contrast to a `DataTable`, it does not offer row grouping, row details, etc. - it's focus
 * is comfortable editing of flat tabular data. Also, the `DataSpreadSheet` has different default
 * settings:
 * 
 * - `editable` is `true` by default
 * - `paginate` is `false` by default 
 * 
 * @author Andrej Kabachnik
 *
 */
class DataSpreadSheet extends Data implements iFillEntireContainer, iTakeInput
{
    use EditableTableTrait;
    
    protected function init()
    {
        parent::init();
        $this->setPaginate(false);
        $this->setEditable(true);
    }
    
    public function getWidth()
    {
        if (parent::getWidth()->isUndefined()) {
            $this->setWidth('max');
        }
        return parent::getWidth();
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::getAlternativeContainerForOrphanedSiblings()
     */
    public function getAlternativeContainerForOrphanedSiblings()
    {
        return null;
    }
}