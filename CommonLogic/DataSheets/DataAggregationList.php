<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Factories\DataAggregationFactory;
use exface\Core\Interfaces\DataSheets\DataAggregationListInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\EntityList;

class DataAggregationList extends EntityList implements DataAggregationListInterface
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
        $data_sheet = $this->getParent();
        foreach ($uxon as $u) {
            $aggr = DataAggregationFactory::createFromUxon($data_sheet, $u);
            $this->add($aggr);
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataAggregationListInterface::addFromString()
     */
    public function addFromString($attribute_alias)
    {
        $data_sheet = $this->getParent();
        $aggr = DataAggregationFactory::createForDataSheet($data_sheet);
        $aggr->setAttributeAlias($attribute_alias);
        $this->add($aggr);
        return $this;
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
}
?>