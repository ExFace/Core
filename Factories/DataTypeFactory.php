<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Exceptions\DataTypes\DataTypeNotFoundError;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\Model\DataTypeInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\ModelInterface;
use exface\Core\Interfaces\AppInterface;

abstract class DataTypeFactory extends AbstractNameResolverFactory
{

    /**
     *
     * @param NameResolverInterface $name_resolver            
     * @return DataTypeInterface
     */
    public static function create(NameResolverInterface $name_resolver)
    {
        if ($name_resolver->classExists()){
            $class = $name_resolver->getClassNameWithNamespace();
            return new $class($name_resolver);
        } else {
            throw new DataTypeNotFoundError('Data type "' . $name_resolver->getAliasWithNamespace() . '" not found in class "' . $name_resolver->getClassNameWithNamespace() . '"!');
        }
    }

    /**
     * 
     * @param Workbench $exface            
     * @param string $alias_with_namespace            
     * @return DataTypeInterface
     */
    public static function createFromAlias(Workbench $workbench, $alias_with_namespace)
    {
        return static::createFromUidOrAlias($workbench->model(), $alias_with_namespace);
    }
    
    /**
     * 
     * @param Workbench $workbench
     * @return DataTypeInterface
     */
    public static function createBaseDataType(Workbench $workbench)
    {
        $name_resolver = NameResolver::createFromString($workbench->getCoreApp()->getAliasWithNamespace() . NameResolver::NAMESPACE_SEPARATOR . 'String', NameResolver::OBJECT_TYPE_DATATYPE, $workbench);
        return static::create($name_resolver);
    }
    
    /**
     * 
     * @param Workbench $workbench
     * @param string $prototype_alias
     * @return \exface\Core\Interfaces\Model\DataTypeInterface
     */
    public static function createFromPrototype(Workbench $workbench, $prototype_resolvable_name)
    {
        return static::create(NameResolver::createFromString($prototype_resolvable_name, NameResolver::OBJECT_TYPE_DATATYPE, $workbench));
    }
    
    /**
     * 
     * @param ModelInterface $model
     * @param string $uid
     * @return \exface\Core\Interfaces\Model\DataTypeInterface
     */
    public static function createFromUidOrAlias(ModelInterface $model, $id_or_alias)
    {
        return $model->getModelLoader()->loadDataType($id_or_alias);
    }
    
    /**
     * 
     * @param string $prototype_alias
     * @param string $alias
     * @param AppInterface $app
     * @param UxonObject $uxon
     * @param string $name
     * @param string $validation_error_code
     * @param UxonObject $default_widget_uxon
     * 
     * @return \exface\Core\Interfaces\Model\DataTypeInterface
     */
    public static function createFromModel($prototype_alias, $alias, AppInterface $app, UxonObject $uxon, $name = null, $short_description = null, $validation_error_code = null, UxonObject $default_widget_uxon = null){
        $data_type = static::createFromPrototype($app->getWorkbench(), $prototype_alias);
        $data_type->setApp($app);
        $data_type->setAlias($alias);
        if ($name !== '' && ! is_null($name)) {
            $data_type->setName($name);
        }
        if ($validation_error_code !== '' && ! is_null($validation_error_code)) {
            $data_type->setValidationErrorCode($validation_error_code);
        }
        if ($short_description !== '' && ! is_null($short_description)) {
            $data_type->setShortDescription($short_description);
        }
        if (! is_null($default_widget_uxon) && ! $default_widget_uxon->isEmpty()) {
            $data_type->setDefaultWidgetUxon($default_widget_uxon);
        }
        $data_type->importUxonObject($uxon);
        return $data_type;
    }
}
?>