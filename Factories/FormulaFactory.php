<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\CommonLogic\Model\Formula;

abstract class FormulaFactory extends AbstractNameResolverFactory
{

    /**
     * Creates a formula from the given name resolver and optionally specified array of arguments
     *
     * @param NameResolverInterface $name_resolver            
     * @param array $arguments            
     * @return Formula
     */
    public static function create(NameResolverInterface $name_resolver, array $arguments = array())
    {
        $class = $name_resolver->getClassNameWithNamespace();
        $workbench = $name_resolver->getWorkbench();
        $formula = new $class($workbench);
        $formula->init($arguments);
        return $formula;
    }

    /**
     * Creates a Formula specified by the function name and an optional array of arguments.
     *
     * @param exface $exface            
     * @param string $function_name            
     * @param array $arguments            
     * @return Formula
     */
    public static function createFromString(Workbench $exface, $function_name, array $arguments = array())
    {
        $name_resolver = $exface->createNameResolver($function_name, NameResolver::OBJECT_TYPE_FORMULA);
        // TODO on linux we have many problems with case sensitivity of file names while formula names are (and should)
        // not be case sensitive - mainly to allow Excel-like notation. Since most parts of UXON are case-insensitive,
        // some sort of dictionary-cache should be built for alias-filename mappings with different case - perhaps in the
        // name resolver. In the specific case of formulas, here is an attempt to guess the class name.
        if (! $name_resolver->classExists()) {
            $name_resolver->setAlias(ucfirst(mb_strtolower($name_resolver->getAlias())));
        }
        return static::create($name_resolver, $arguments);
    }
}
?>