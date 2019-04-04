<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\ModelInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Selectors\DataTypeSelectorInterface;
use exface\Core\CommonLogic\Selectors\DataTypeSelector;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Exceptions\DataTypes\DataTypeNotFoundError;

abstract class DataTypeFactory extends AbstractSelectableComponentFactory
{

    /**
     *
     * @param DataTypeSelectorInterface $name_resolver            
     * @return DataTypeInterface
     */
    public static function create(DataTypeSelectorInterface $selector) : DataTypeInterface
    {
        return static::createFromSelector($selector);
    }
    
    /**
     * 
     * @param SelectorInterface $selector
     * @return \exface\Core\Interfaces\DataTypes\DataTypeInterface
     */
    public static function createFromSelector(SelectorInterface $selector)
    {
        if ($selector->isUid()) {
            return $selector->getWorkbench()->model()->getModelLoader()->loadDataType($selector);
        } else {
            return parent::createFromSelector($selector);
        }
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @return DataTypeInterface
     */
    public static function createBaseDataType(WorkbenchInterface $workbench) : DataTypeInterface
    {
        $type = StringDataType::class;
        $selector = new DataTypeSelector($workbench, $type);
        return static::create($selector);
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $prototypeSelector
     * @return \exface\Core\Interfaces\DataTypes\DataTypeInterface
     */
    public static function createFromPrototype(WorkbenchInterface $workbench, string $prototypeSelector) : DataTypeInterface
    {
        return static::createFromSelector(new DataTypeSelector($workbench, $prototypeSelector));
    }
    
    /**
     * 
     * @param ModelInterface $model
     * @param string $uid
     * @return \exface\Core\Interfaces\DataTypes\DataTypeInterface
     */
    public static function createFromString(WorkbenchInterface $workbench, string $selectorString) : DataTypeInterface
    {
        $selector = new DataTypeSelector($workbench, $selectorString);
        return static::createFromSelector($selector);
    }
    
    /**
     * 
     * @param string $prototype_alias
     * @param string $alias
     * @param AppInterface $app
     * @param UxonObject $uxon
     * @param string $name
     * @param string $validation_error_code
     * @param UxonObject $default_editor_uxon
     * 
     * @return \exface\Core\Interfaces\DataTypes\DataTypeInterface
     */
    public static function createFromModel($prototype_alias, $alias, AppInterface $app, UxonObject $uxon, $name = null, $short_description = null, $validation_error_code = null, $validation_error_text = null, UxonObject $default_editor_uxon = null) : DataTypeInterface
    {
        $data_type = static::createFromPrototype($app->getWorkbench(), $prototype_alias);
        $data_type->setApp($app);
        $data_type->setAlias($alias);
        if ($name !== '' && ! is_null($name)) {
            $data_type->setName($name);
        }
        if ($validation_error_code !== '' && ! is_null($validation_error_code)) {
            $data_type->setValidationErrorCode($validation_error_code);
        }
        if (! is_null($validation_error_text)) {
            $data_type->setValidationErrorText($validation_error_text);
        }
        if ($short_description !== '' && ! is_null($short_description)) {
            $data_type->setShortDescription($short_description);
        }
        if (! is_null($default_editor_uxon) && ! $default_editor_uxon->isEmpty()) {
            $data_type->setDefaultEditorUxon($default_editor_uxon);
        }
        $data_type->importUxonObject($uxon);
        return $data_type;
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param UxonObject $uxon
     * @throws DataTypeNotFoundError
     * @return DataTypeInterface
     */
    public static function createFromUxon(WorkbenchInterface $workbench, UxonObject $uxon) : DataTypeInterface
    {
        $alias = $uxon->getProperty('alias');
        
        if (! $alias) {
            throw new DataTypeNotFoundError('Cannot create data type from UXON: missing alias!');
        }
        
        $selector = new DataTypeSelector($workbench, $alias);
        $type = static::create($selector);
        $type->importUxonObject($uxon);
        return $type;
    }
}
?>