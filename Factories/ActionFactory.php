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
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;

/**
 * Instantiates actions
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class ActionFactory extends AbstractStaticFactory
{

    /**
     * Instantiates a new action from the given selector.
     * 
     * If the app determined from the selector can supply a prototype, it will be used. If not,
     * a search over object actions in the metamodel will be performed.
     *
     * @param ActionSelectorInterface $selector            
     * @return ActionInterface
     */
    public static function create(ActionSelectorInterface $selector, WidgetInterface $trigger_widget = null, UxonObject $uxon = null) : ActionInterface
    {
        $app = $selector->getWorkbench()->getApp($selector->getAppSelector());
        if ($app->has($selector)) {
            $action = static::createFromPrototype($selector, $app, $trigger_widget);
        } else {
            $actionAlias = substr($selector->toString(), (strlen($selector->getAppAlias())+1));
            $action = $selector->getWorkbench()->model()->getModelLoader()->loadAction($app, $actionAlias, $trigger_widget);
            if (! $action) {
                throw new ActionNotFoundError('Cannot find action "' . $selector->toString() . '" in app "' . $selector->getAppAlias() . '"!');
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
    public static function createFromUxon(Workbench $workbench, UxonObject $uxon, WidgetInterface $trigger_widget = null) : ActionInterface
    {
        if (! $action_alias = $uxon->getProperty('alias')) {
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
    public static function createFromString(Workbench $workbench, $qualified_alias_or_class_or_file, AbstractWidget $trigger_widget = null) : ActionInterface
    {
        $selector = new ActionSelector($workbench, $qualified_alias_or_class_or_file);
        return static::create($selector, $trigger_widget);
    }

    /**
     * Instantiates an action based on the selector for a prototype (= PHP class). 
     * 
     * NOTE: This method does not search for object actions or any other action models. It assumes,
     * that the given selector fits a prototype and attempts to get the action from the app
     * determined from the selector directly.
     * 
     * @param ActionSelectorInterface $selector            
     * @param AppInterface $app
     * 
     * @return ActionInterface
     */
    public static function createFromPrototype(ActionSelectorInterface $selector, AppInterface $app = null, WidgetInterface $trigger_widget = null) : ActionInterface
    {
        $app = $app ? $app : $selector->getWorkbench()->getApp($selector->getAppSelector());
        return $app->getAction($selector, $trigger_widget);
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
    public static function createFromModel($prototype_alias, $action_alias, AppInterface $app, MetaObjectInterface $object, UxonObject $uxon_description = null, WidgetInterface $trigger_widget = null) : ActionInterface
    {
        $selector = new ActionSelector($app->getWorkbench(), $prototype_alias);
        $action = static::createFromPrototype($selector, $app, $trigger_widget);
        $action->setAlias($action_alias);
        $action->setMetaObject($object);
        if (! is_null($uxon_description)) {
            $action->importUxonObject($uxon_description);
        }
        return $action;
    }
}