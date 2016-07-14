<?php namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\TemplateInterface;

abstract class TemplateFactory extends AbstractNameResolverFactory {
	
	/**
	 * 
	 * @param NameResolverInterface $name_resolver
	 * @return TemplateInterface
	 */
	public static function create(NameResolverInterface $name_resolver){
		$exface = $name_resolver->get_workbench();
		$class = $name_resolver->get_class_name_with_namespace();
		$template = new $class($exface);
		$template->set_name_resolver($name_resolver);
		return $template;
	}
	
	/**
	 * @param string $qualified_alias
	 * @param exface $exface
	 * @return TemplateInterface
	 */
	public static function create_from_string($qualified_alias, Workbench &$exface){
		$name_resolver = NameResolver::create_from_string($qualified_alias, NameResolver::OBJECT_TYPE_TEMPLATE, $exface);
		return static::create($name_resolver);
	}
	
	/**
	 * 
	 * @param string|NameResolverInterface|TemplateInterface $name_reslver_or_alias_or_template
	 * @param exface $exface
	 * @return \exface\Core\Interfaces\TemplateInterface
	 */
	public static function create_from_anything($name_reslver_or_alias_or_template, Workbench &$exface){
		if ($name_reslver_or_alias_or_template instanceof TemplateInterface){
			$template = $name_reslver_or_alias_or_template;
		} elseif ($name_reslver_or_alias_or_template instanceof NameResolverInterface){
			$template = static::create($name_reslver_or_alias_or_template);
		} else {
			$template = static::create_from_string($name_reslver_or_alias_or_template, $exface);
		}
		return $template;
	}
}
?>