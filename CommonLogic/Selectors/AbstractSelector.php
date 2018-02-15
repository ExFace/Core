<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\Selectors\SelectorInterface;

abstract class AbstractSelector implements SelectorInterface
{
    const ALIAS_NAMESPACE_DELIMITER = '.';
    
    private $workbench = null;
    
    private $selectorString = null;
    
    public function __construct(Workbench $workbench, $selectorString)
    {
        // TODO Check if string empty and throw exception
        $this->workbench = $workbench;
        $this->selectorString = $selectorString;
    }
    
    public function __toString()
    {
        return $this->selectorString;
    }
    
    public static function getAppAliasFromNamespace($aliasWithNamespace)
    {
        $parts = explode(static::getAliasNamespaceDelimiter(), $aliasWithNamespace);
        if (count($parts) < 3) {
            return false;
        }
        return implode(static::getAliasNamespaceDelimiter(), array_slice($parts, 0, 2));
    }
    
    public static function getAliasNamespaceDelimiter()
    {
        return self::ALIAS_NAMESPACE_DELIMITER;
    }
}