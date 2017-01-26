<?php namespace exface\Core\Factories;

use exface;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder;
use exface\Core\Interfaces\NameResolverInterface;

abstract class QueryBuilderFactory extends AbstractNameResolverFactory {
	
	/**
	 * Creates a new query based on the given name resolver
	 * @param NameResolverInterface $name_resolver
	 * @return AbstractQueryBuilder
	 */
	public static function create(NameResolverInterface $name_resolver){
		return parent::create($name_resolver);
	}
	
	/**
	 * Creates a new query (query builder instance) from the given identifier 
	 * - file path relative to the ExFace installation directory
	 * - ExFace alias with namespace
	 * - class name
	 * @param exface\Core\CommonLogic\Workbench $exface
	 * @param string $alias_with_namespace
	 * @return AbstractQueryBuilder
	 */
	public static function create_from_alias(exface\Core\CommonLogic\Workbench $exface, $path_or_qualified_alias){
		$name_resolver = $exface->create_name_resolver($path_or_qualified_alias, NameResolver::OBJECT_TYPE_QUERY_BUILDER);
		return static::create($name_resolver);
	}
}
?>