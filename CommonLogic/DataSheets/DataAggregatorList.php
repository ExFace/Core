<?php

namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Factories\DataAggregatorFactory;
use exface\Core\Interfaces\DataSheets\DataAggregatorListInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\EntityList;

class DataAggregatorList extends EntityList implements DataAggregatorListInterface
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
        foreach ($this->getAll() as $aggr) {
            $aggr->setDataSheet($data_sheet);
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
        if (is_array($uxon->getProperty('aggregators'))) {
            $this->importUxonArray($uxon->getProperty('aggregators'));
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataAggregatorListInterface::importUxonArray()
     */
    public function importUxonArray(array $uxon)
    {
        $data_sheet = $this->getParent();
        foreach ($uxon as $u) {
            $aggr = DataAggregatorFactory::createFromUxon($data_sheet, $u);
            $this->add($aggr);
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataAggregatorListInterface::addFromString()
     */
    public function addFromString($attribute_alias)
    {
        $data_sheet = $this->getParent();
        $aggr = DataAggregatorFactory::createForDataSheet($data_sheet);
        $aggr->setAttributeAlias($attribute_alias);
        $this->add($aggr);
        return $this;
    }

    /**
     * Returns the data sheet, the list belongs to.
     * This is a better understandable alias for the inherited get_parent()
     * 
     * @return DataSheetInterface
     */
    public function getDataSheet()
    {
        return $this->getParent();
    }
}
?>