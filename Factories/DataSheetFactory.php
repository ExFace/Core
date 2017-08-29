<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\DataSheets\DataSheet;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\InvalidArgumentException;

abstract class DataSheetFactory extends AbstractUxonFactory
{

    /**
     * Creates a data sheet for a give object.
     * The object can be passed directly or specified by it's fully qualified alias (with namespace!)
     *
     * @param Workbench $exface            
     * @param Object|string $meta_object_or_alias            
     * @return DataSheetInterface
     */
    public static function createFromObjectIdOrAlias(Workbench $exface, $meta_object_or_alias = null)
    {
        if ($meta_object_or_alias instanceof Object) {
            $meta_object = $meta_object_or_alias;
        } else {
            $meta_object = $exface->model()->getObject($meta_object_or_alias);
        }
        return static::createFromObject($meta_object);
    }

    /**
     *
     * @param Workbench $exface            
     * @return DataSheetInterface
     */
    public static function createEmpty(Workbench $exface)
    {
        return static::createFromObjectIdOrAlias($exface);
    }

    /**
     *
     * @param Object $meta_object            
     * @return DataSheetInterface
     */
    public static function createFromObject(Object $meta_object)
    {
        $data_sheet = new DataSheet($meta_object);
        return $data_sheet;
    }

    /**
     *
     * @param Workbench $exface            
     * @param UxonObject $uxon            
     * @return DataSheetInterface
     */
    public static function createFromUxon(Workbench $exface, UxonObject $uxon)
    {
        $object_ref = $uxon->hasProperty('object_alias') ? $uxon->getProperty('object_alias') : $uxon->getProperty('meta_object_alias');
        if (!$object_ref){
            $object_ref = $uxon->hasProperty('meta_object_id') ? $uxon->getProperty('meta_object_id') : $uxon->getProperty('oId');
        }
        $meta_object = $exface->model()->getObject($object_ref);
        $data_sheet = self::createFromObject($meta_object);
        $data_sheet->importUxonObject($uxon);
        return $data_sheet;
    }

    /**
     *
     * @param Workbench $exface            
     * @param DataSheetInterface|UxonObject|\stdClass $data_sheet_or_uxon            
     * @throws InvalidArgumentException
     * @return DataSheetInterface
     */
    public static function createFromAnything(Workbench $exface, $data_sheet_or_uxon)
    {
        if ($data_sheet_or_uxon instanceof DataSheetInterface) {
            return $data_sheet_or_uxon;
        } elseif ($data_sheet_or_uxon instanceof \stdClass) {
            return static::createFromStdClass($exface, $data_sheet_or_uxon);
        } elseif (! is_object($data_sheet_or_uxon)) {
            return static::createFromUxon($exface, UxonObject::fromJson($data_sheet_or_uxon));
        } else {
            throw new InvalidArgumentException('Cannot create data sheet from "' . get_class($data_sheet_or_uxon) . '"!');
        }
    }
}
?>