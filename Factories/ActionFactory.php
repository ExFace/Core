<?php namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Widgets\AbstractWidget;
use exface\Core\Exceptions\UxonParserError;
use exface\Core\CommonLogic\UxonObject;

abstract class ActionFactory extends AbstractNameResolverFactory {
	
	/**
	 * Creates a new action from the given name resolver
	 * @param NameResolver $name_resolver
	 * @return ActionInterface
	 */
	public static function create(NameResolverInterface $name_resolver, AbstractWidget $called_by_widget = null, \stdClass $uxon_description = null){
		$app = $name_resolver->get_workbench()->get_app($name_resolver->get_namespace());
		$class = $name_resolver->get_class_name_with_namespace();
		$action = new $class($app);
		if ($called_by_widget){
			$action->set_called_by_widget($called_by_widget);
		}
		if ($uxon_description instanceof \stdClass){
			$action->import_uxon_object($uxon_description);
		}
		return $action;
	}
	
	/**
	 * 
	 * @param exface $exface
	 * @param UxonObject $uxon_description
	 * @param AbstractWidget $called_by_widget
	 * @throws UxonParserError
	 * @return ActionInterface
	 */
	public static function create_from_uxon(Workbench &$exface, \stdClass $uxon_description, AbstractWidget $called_by_widget = null){
		if ($action_alias = $uxon_description->alias){
			unset($uxon_description->alias);
		} else {
			throw new UxonParserError('Cannot instantiate action from UXON: no action alias found in "' . print_r($uxon_description) . '"!');
		}
		$name_resolver = $exface->create_name_resolver($action_alias, NameResolver::OBJECT_TYPE_ACTION);
		$action = static::create($name_resolver, $called_by_widget, $uxon_description);
		return $action;
	}
	
	/**
	 * 
	 * @param exface $exface
	 * @param string $qualified_action_alias
	 * @param AbstractWidget $called_by_widget
	 * @return ActionInterface
	 */
	public static function create_from_string(Workbench &$exface, $qualified_action_alias, AbstractWidget $called_by_widget = null){
		$name_resolver = $exface->create_name_resolver($qualified_action_alias, NameResolver::OBJECT_TYPE_ACTION);
		return static::create($name_resolver, $called_by_widget);
	}
	
}
?>