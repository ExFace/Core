<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Factories\DataSorterFactory;
use exface\Core\Interfaces\DataSheets\DataSorterListInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\EntityList;
use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\DataSheets\DataSheetStructureError;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class DataSorterList extends EntityList implements DataSorterListInterface
{

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\EntityList::setParent()
     */
    public function setParent($data_sheet)
    {
        $result = parent::setParent($data_sheet);
        foreach ($this->getAll() as $sorter) {
            $sorter->setDataSheet($data_sheet);
        }
        return $result;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\EntityList::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon)
    {
        $data_sheet = $this->getParent();
        foreach ($uxon as $u) {
            $instance = DataSorterFactory::createFromUxon($data_sheet, $u);
            $this->add($instance);
        }
        return;
    }

    /**
     * Returns the data sheet, the list belongs to.
     * This is a better understandable alias for the inherited getParent()
     *
     * @return DataSheetInterface
     */
    public function getDataSheet()
    {
        return $this->getParent();
    }

    /**
     * Adds a new sorter to the the list, creating it either from a column_id or an attribute alias.
     *
     * TODO The possiblity to sort over a column name (QTY_SUM) and the corresponding expression (QTY:SUM) causes trouble. The solution here is quite dirty.
     * A better way would probably be to take care of the different expressions before adding the sorter to the data sheet or makeing two separate methods.
     *
     * @param string $attribute_alias_or_column_id            
     * @param string $direction            
     * @return DataSorterListInterface
     */
    public function addFromString($attribute_alias_or_column_id, $direction = 'ASC')
    {
        // If the sorter is not just a simple attribute, try to find the attribute in the column corresponding to the sorter
        // This helps if the id of the column is passed instead of the expression.
        $data_sheet = $this->getDataSheet();
        try {
            $attr = $data_sheet->getMetaObject()->getAttribute($attribute_alias_or_column_id);
        } catch (ErrorExceptionInterface $e) {
            // No need to do anything here, because $attr will automatically remain NULL
        }
        
        if (! $attr) {
            if ($col = $data_sheet->getColumn($attribute_alias_or_column_id)) {
                if ($col->getExpressionObj()->isMetaAttribute()) {
                    $attribute_alias = $col->getExpressionObj()->toString();
                } else {
                    $attrs = $col->getExpressionObj()->getRequiredAttributes();
                    if (count($attrs) > 0) {
                        $attribute_alias = reset($attrs);
                    }
                }
            }
        } else {
            $attribute_alias = $attribute_alias_or_column_id;
        }
        
        if (! $attribute_alias) {
            throw new DataSheetStructureError($this->getDataSheet(), 'Cannot add a sorter over "' . $attribute_alias_or_column_id . '" to data sheet with object "' . $this->getDataSheet()->getMetaObject()->getAliasWithNamespace() . '": no matching attribute could be found!', '6UQBX9K');
        }
        
        $sorter = DataSorterFactory::createForDataSheet($data_sheet);
        $sorter->setAttributeAlias($attribute_alias);
        $sorter->setDirection($direction);
        $this->add($sorter);
        
        return $this;
    }
}
?>