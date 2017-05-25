<?php

namespace exface\Core\Events;

use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * Data sheet event names consist of the qualified alias of the base meta object followed by "DataSheet" and the respective event type:
 * e.g.
 * exface.Core.Object.DataSheet.UpdateData.Before, etc.
 * 
 * @author Andrej Kabachnik
 *        
 */
class DataSheetEvent extends ExfaceEvent
{

    private $data_sheet = null;

    /**
     *
     * @return DataSheetInterface
     */
    public function getDataSheet()
    {
        return $this->data_sheet;
    }

    /**
     *
     * @param DataSheetInterface $value            
     * @return DataSheetEvent
     */
    public function setDataSheet(DataSheetInterface $value)
    {
        $this->data_sheet = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Events\ExfaceEvent::getNamespace()
     */
    public function getNamespace()
    {
        return $this->getDataSheet()
            ->getMetaObject()
            ->getAliasWithNamespace() . NameResolver::NAMESPACE_SEPARATOR . 'DataSheet';
    }
}