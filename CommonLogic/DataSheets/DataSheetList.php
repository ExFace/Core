<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Interfaces\DataSheets\DataSheetListInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\EntityList;
use exface\Core\Exceptions\DataSheets\DataSheetLogicError;

class DataSheetList extends EntityList implements DataSheetListInterface
{

    /**
     * Adds a data sheet
     *
     * @param DataSheet $column            
     * @param mixed $key            
     * @return DataSheetList
     */
    public function add($sheet, $key = null)
    {
        if ($sheet instanceof DataSheetSubsheet) {
            $result = parent::add($sheet, $key);
        } else {
            throw new DataSheetLogicError($this, 'Adding regular data sheets as subsheets not implemented yet!');
        }
        return $result;
    }

    /**
     *
     * @return DataSheetInterface[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    /**
     * Returns all subsheets, that have the specified meta object as their base object
     *
     * @param MetaObjectInterface $object            
     * @return DataColumn[]
     */
    public function getByObject(MetaObjectInterface $object)
    {
        $result = array();
        foreach ($this->getAll() as $sheet) {
            if ($sheet->getMetaObject()->getId() == $object->getId()) {
                $result[] = $sheet;
            }
        }
        return $result;
    }

    /**
     * Returns the data sheet, that was stored under the given key
     *
     * @param mixed $key            
     * @return DataSheetInterface
     */
    public function get($key)
    {
        return parent::get($key);
    }

    /**
     * Returns the parent data sheet
     *
     * @return DataSheetInterface
     */
    public function getParent()
    {
        return parent::getParent();
    }
}