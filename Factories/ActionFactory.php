<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Widgets\AbstractWidget;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Exceptions\UxonParserError;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Exceptions\Actions\ActionNotFoundError;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Selectors\ActionSelectorInterface;
use exface\Core\CommonLogic\Selectors\ActionSelector;
use exface\Core\Interfaces\Selectors\SelectorInterface;

/**
 * Instantiates actions
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class ActionFactory extends AbstractSelectorFactory
{

    /**
     * Instantiates a new action from the given selector
     *
     * @param ActionSelectorInterface $selector            
     * @return ActionInterface
     */
    public static function create(SelectorInterface $selector, WidgetInterface $trigger_widget = null, UxonObject $uxon = null)
    {
        $app = $selector->getWorkbench()->getApp($selector->getAppAlias());
        if ($selector->prototypeClassExists()) {
            $action = static::createEmpty($selector, $app, $trigger_widget);
        } else {
            $action = $selector->getWorkbench()->model()->getModelLoader()->loadAction($app, $selector->getAlias(), $trigger_widget);
            if (! $action) {
                throw new ActionNotFoundError('Cannot find action "' . $selector->getAliasWithNamespace() . '"!');
            }
        }
        if ($uxon instanceof UxonObject) {
            $action->importUxonObject($uxon);
        }
        return $action;
    }

    /**
     *
     * @param Workbench $workbench            
     * @param UxonObject $uxon            
     * @param AbstractWidget $trigger_widget            
     * @throws UnexpectedValueException
     * @return ActionInterface
     */
    public static function createFromUxon(Workbench $workbench, UxonObject $uxon, WidgetInterface $trigger_widget = null)
    {
        if ($action_alias = $uxon->getProperty('alias')) {
            $uxon->unsetProperty('alias');
        } else {
            throw new UxonParserError($uxon, 'Cannot instantiate action from UXON: no action alias found!');
        }
        $selector = new ActionSelector($workbench, $action_alias);
        $action = static::create($selector, $trigger_widget, $uxon);
        return $action;
    }

    /**
     *
     * @param Workbench $workbench            
     * @param string $qualified_action_alias            
     * @param UxonParserError $trigger_widget            
     * @return ActionInterface
     */
    public static function createFromString(Workbench $workbench, $qualified_alias_or_class_or_file, AbstractWidget $trigger_widget = null)
    {
        $selector = new ActionSelector($workbench, $qualified_alias_or_class_or_file);
        return static::create($selector, $trigger_widget);
    }

    /**
     *
     * @param ActionSelectorInterface $selector            
     * @param AppInterface $app            
     * @throws ActionNotFoundError if the class name cannot be resolved
     * @return ActionInterface
     */
    public static function createEmpty(ActionSelectorInterface $selector, AppInterface $app = null, WidgetInterface $trigger_widget = null)
    {
        $app = $app ? $app : $selector->getWorkbench()->getApp($selector->getNamespace());
        if (! $selector->prototypeClassExists()) {
            throw new ActionNotFoundError('Cannot find action "' . $selector->getAliasWithNamespace() . '": class "' . $selector->getClassname() . '" not found!');
        }
        $class = $selector->getClassname();
        return new $class($app, $trigger_widget);
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
    public static function createFromModel($prototype_alias, $action_alias, AppInterface $app, MetaObjectInterface $object, UxonObject $uxon_description = null, WidgetInterface $trigger_widget = null)
    {
        $selector = new ActionSelector($app->getWorkbench(), $prototype_alias);
        $action = static::createEmpty($selector, $app, $trigger_widget);
        $action->setAlias($action_alias);
        $action->setMetaObject($object);
        if (! is_null($uxon_description)) {
            $action->importUxonObject($uxon_description);
        }
        return $action;
    }
}
?>