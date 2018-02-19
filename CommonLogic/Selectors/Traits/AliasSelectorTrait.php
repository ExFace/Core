<?php
namespace exface\Core\CommonLogic\Selectors\Traits;

use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Exceptions\Selectors\SelectorInvalidError;

/**
 * Trait with shared logic for the AliasSelectorInterface
 *
 * @author Andrej Kabachnik
 *
 */
trait AliasSelectorTrait
{
    private $aliasParts = null;
    
    /**
     * 
     * @param string $aliasWithNamespace
     * @return boolean|string
     */
    public static function getAppAliasFromNamespace($aliasWithNamespace)
    {
        $parts = explode(static::getAliasNamespaceDelimiter(), $aliasWithNamespace);
        if (count($parts) < 3) {
            return false;
        }
        return implode(static::getAliasNamespaceDelimiter(), array_slice($parts, 0, 2));
    }
    
    /**
     * 
     * @return string
     */
    public static function getAliasNamespaceDelimiter()
    {
        return AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER;
    }
    
    /**
     * Returns an array with alias parts (basically explode('.', $alias)).
     * 
     * @throws SelectorInvalidError
     * 
     * @return string[]
     */
    protected function getAliasParts()
    {
        if (is_null($this->aliasParts)) {
            $parts = explode($this::getAliasNamespaceDelimiter(), $this->getAliasWithNamespace());
            if (! $this->validateAliasParts($parts)) {
                throw new SelectorInvalidError('"' . $this->getAliasWithNamespace() . '" is not a valid alias!');
            }
            $this->aliasParts = $parts;
        }
        return $this->aliasParts;
    }
    
    /**
     * Returns TRUE if the given array contains parts of a valid alias and FALSE otherwise
     * 
     * @param array $parts
     * @return boolean
     */
    protected function validateAliasParts(array $parts)
    {
        return count($parts) < 3 ? false : true;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\AliasSelectorInterface::getVendorAlias()
     */
    public function getVendorAlias()
    {
        return $this->getAliasParts()[0];
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\AliasSelectorInterface::getAppAlias()
     */
    public function getAppAlias()
    {
        return implode($this::getAliasNamespaceDelimiter(), array_slice($this->getAliasParts(), 0, 2));
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\AliasSelectorInterface::getNamespace()
     */
    public function getNamespace()
    {
        return implode($this::getAliasNamespaceDelimiter(), array_slice($this->getAliasParts(), 0, -1));
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\AliasSelectorInterface::getAlias()
     */
    public function getAlias()
    {
        return array_slice($this->getAliasParts(), -1)[0];
    }
    
    public function isAlias()
    {
        try {
            $this->getAliasParts();
        } catch (SelectorInvalidError $e) {
            return false;
        }
        return true;
    }
}