<?php namespace exface\Core\Factories;

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

abstract class ActionFactory extends AbstractNameResolverFactory {
	
	/**
	 * Creates a new action from the given name resolver
	 * @param NameResolver $name_resolver
	 * @return ActionInterface
	 */
	public static function create(NameResolverInterface $name_resolver, AbstractWidget $called_by_widget = null, \stdClass $uxon_description = null){
		$app = $name_resolver->get_workbench()->get_app($name_resolver->get_namespace());
		if ($name_resolver->class_exists()){
			$action = static::create_empty($name_resolver, $app);
		} else {
			$action = $name_resolver->get_workbench()->model()->get_model_loader()->load_action($app, $name_resolver->get_alias());
			if (!$action){
				throw new ActionNotFoundError('Cannot find action "' . $name_resolver->get_alias_with_namespace() . '"!');
			}
		}
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
	 * @throws UnexpectedValueException
	 * @return ActionInterface
	 */
	public static function create_from_uxon(Workbench $exface, \stdClass $uxon_description, AbstractWidget $called_by_widget = null){
		if ($action_alias = $uxon_description->alias){
			unset($uxon_description->alias);
		} else {
			throw new UxonParserError($uxon_description, 'Cannot instantiate action from UXON: no action alias found!');
		}
		$name_resolver = $exface->create_name_resolver($action_alias, NameResolver::OBJECT_TYPE_ACTION);
		$action = static::create($name_resolver, $called_by_widget, $uxon_description);
		return $action;
	}
	
	/**
	 * 
	 * @param exface $exface
	 * @param string $qualified_action_alias
	 * @param UxonParserError $called_by_widget
	 * @return ActionInterface
	 */
	public static function create_from_string(Workbench $exface, $qualified_alias_or_class_or_file, AbstractWidget $called_by_widget = null){
		$name_resolver = static::get_name_resolver_from_string($exface, $qualified_alias_or_class_or_file);
		return static::create($name_resolver, $called_by_widget);
	}	
	
	protected static function get_name_resolver_from_string(Workbench $exface, $alias_or_class_or_file){
		return $exface->create_name_resolver($alias_or_class_or_file, NameResolver::OBJECT_TYPE_ACTION);
	}
	
	/**
	 * 
	 * @param NameResolverInterface $name_resolver
	 * @param AppInterface $app
	 * @throws ActionNotFoundError if the class name cannot be resolved
	 * @return ActionInterface
	 */
	public static function create_empty(NameResolverInterface $name_resolver, AppInterface $app = null){
		$app = $app ? $app : $name_resolver->get_workbench()->get_app($name_resolver->get_namespace());
		if (!$name_resolver->class_exists()){
			throw new ActionNotFoundError('Cannot find action "' . $name_resolver->get_alias_with_namespace() . '": class "' . $name_resolver->get_class_name_with_namespace() . '" not found!');
		}
		$class = $name_resolver->get_class_name_with_namespace();
		return new $class($app);
	}
	
	/**
	 * 
	 * @param string $base_action_alias_or_class_or_file
	 * @param string $action_alias
	 * @param AppInterface $app
	 * @param UxonObject $uxon_description
	 * @throws ActionNotFoundError if the class name of the base action cannot be resolved
	 * @return \exface\Core\Interfaces\Actions\ActionInterface
	 */
	public static function create_from_model($base_action_alias_or_class_or_file, $action_alias, AppInterface $app, UxonObject $uxon_description = null){
		$name_resolver = static::get_name_resolver_from_string($app->get_workbench(), $base_action_alias_or_class_or_file);
		$action = static::create_empty($name_resolver, $app);
		$action->set_alias($action_alias);
		if (!is_null($uxon_description)){
			$action->import_uxon_object($uxon_description);
		}
		return $action;
	}
}
?>