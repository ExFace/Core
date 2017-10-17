<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Widgets\AbstractWidget;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Exceptions\UxonParserError;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Exceptions\Actions\ActionNotFoundError;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WidgetInterface;

abstract class ActionFactory extends AbstractNameResolverFactory
{

    /**
     * Creates a new action from the given name resolver
     *
     * @param NameResolver $name_resolver            
     * @return ActionInterface
     */
    public static function create(NameResolverInterface $name_resolver, AbstractWidget $called_by_widget = null, UxonObject $uxon_description = null)
    {
        $app = $name_resolver->getWorkbench()->getApp($name_resolver->getNamespace());
        if ($name_resolver->classExists()) {
            $action = static::createEmpty($name_resolver, $app, $called_by_widget);
        } else {
            $action = $name_resolver->getWorkbench()->model()->getModelLoader()->loadAction($app, $name_resolver->getAlias(), $called_by_widget);
            if (! $action) {
                throw new ActionNotFoundError('Cannot find action "' . $name_resolver->getAliasWithNamespace() . '"!');
            }
        }
        if ($uxon_description instanceof UxonObject) {
            $action->importUxonObject($uxon_description);
        }
        return $action;
    }

    /**
     *
     * @param Workbench $exface            
     * @param UxonObject $uxon_description            
     * @param AbstractWidget $called_by_widget            
     * @throws UnexpectedValueException
     * @return ActionInterface
     */
    public static function createFromUxon(Workbench $exface, UxonObject $uxon_description, AbstractWidget $called_by_widget = null)
    {
        if ($action_alias = $uxon_description->getProperty('alias')) {
            $uxon_description->unsetProperty('alias');
        } else {
            throw new UxonParserError($uxon_description, 'Cannot instantiate action from UXON: no action alias found!');
        }
        $name_resolver = $exface->createNameResolver($action_alias, NameResolver::OBJECT_TYPE_ACTION);
        $action = static::create($name_resolver, $called_by_widget, $uxon_description);
        return $action;
    }

    /**
     *
     * @param Workbench $exface            
     * @param string $qualified_action_alias            
     * @param UxonParserError $called_by_widget            
     * @return ActionInterface
     */
    public static function createFromString(Workbench $exface, $qualified_alias_or_class_or_file, AbstractWidget $called_by_widget = null)
    {
        $name_resolver = static::getNameResolverFromString($exface, $qualified_alias_or_class_or_file);
        return static::create($name_resolver, $called_by_widget);
    }

    protected static function getNameResolverFromString(Workbench $exface, $alias_or_class_or_file)
    {
        return $exface->createNameResolver($alias_or_class_or_file, NameResolver::OBJECT_TYPE_ACTION);
    }

    /**
     *
     * @param NameResolverInterface $name_resolver            
     * @param AppInterface $app            
     * @throws ActionNotFoundError if the class name cannot be resolved
     * @return ActionInterface
     */
    public static function createEmpty(NameResolverInterface $name_resolver, AppInterface $app = null, WidgetInterface $called_by_widget = null)
    {
        $app = $app ? $app : $name_resolver->getWorkbench()->getApp($name_resolver->getNamespace());
        if (! $name_resolver->classExists()) {
            throw new ActionNotFoundError('Cannot find action "' . $name_resolver->getAliasWithNamespace() . '": class "' . $name_resolver->getClassNameWithNamespace() . '" not found!');
        }
        $class = $name_resolver->getClassNameWithNamespace();
        return new $class($app, $called_by_widget);
    }

    /**
     *
     * @param string $base_action_alias_or_class_or_file            
     * @param string $action_alias            
     * @param AppInterface $app            
     * @param MetaObjectInterface $object            
     * @param UxonObject $uxon_description            
     * @throws ActionNotFoundError if the class name of the base action cannot be resolved
     * @return \exface\Core\Interfaces\Actions\ActionInterface
     */
    public static function createFromModel($prototype_alias, $action_alias, AppInterface $app, MetaObjectInterface $object, UxonObject $uxon_description = null, WidgetInterface $called_by_widget = null)
    {
        $name_resolver = static::getNameResolverFromString($app->getWorkbench(), $prototype_alias);
        $action = static::createEmpty($name_resolver, $app, $called_by_widget);
        $action->setAlias($action_alias);
        $action->setMetaObject($object);
        if (! is_null($uxon_description)) {
            $action->importUxonObject($uxon_description);
        }
        return $action;
    }
}
?>