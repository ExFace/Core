<?php
namespace exface\Core\CommonLogic\Selectors\Traits;

use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Exceptions\Selectors\SelectorInvalidError;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\CommonLogic\Selectors\AppSelector;
use exface\Core\DataTypes\StringDataType;

/**
 * Trait with shared logic for the AliasSelectorInterface
 *
 * @author Andrej Kabachnik
 *
 */
trait AliasSelectorTrait
{
    private $splitParts = null;
    
    private $isAlias = null;
    
    /**
     * Returns the app alias from a (potentially very long) namespace or NULL if the selector has no namespace.
     * 
     * The app alias consists of the first two elements of the namespace.
     * 
     * @param string $aliasWithNamespace
     * @return string|NULL
     */
    protected static function getAppAliasFromNamespace($aliasWithNamespace) : ?string
    {
        $parts = explode(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, $aliasWithNamespace);
        if (count($parts) < 3) {
            return null;
        }
        return implode(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, array_slice($parts, 0, 2));
    }
    
    /**
     * Removes the entire namespace from the given alias selector leaving only the alias.
     * 
     * Examples:
     * - returns `OBJECT` for `exface.Core.OBJECT`
     * - returns `NON_NAMESPACED_ALIAS` for `NON_NAMESPACED_ALIAS`
     * 
     * @param string $aliasWithNamespace
     * @return string
     */
    public static function stripNamespace(string $aliasWithNamespace) : string
    {
        return StringDataType::substringAfter($aliasWithNamespace, AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, $aliasWithNamespace, false, true);
    }
    
    /**
     * Returns the namspace of the given namespaced alias (the part before the last dot).
     * 
     * Returns NULL if the alias has no namespace.
     * 
     * Examples:
     * - returns `exface.Core` for `exface.Core.OBJECT`
     * - returns `NULL` for `NON_NAMESPACED_ALIAS`
     * 
     * @param string $aliasWithNamespace
     * @return string
     */
    public static function findNamespace(string $aliasWithNamespace) : ?string
    {
        return StringDataType::substringBefore($aliasWithNamespace, AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, null, false, true);
    }
    
    /**
     * Returns an array with alias parts - by default explode('.', $alias), but override this to support other selectors.
     * 
     * @throws SelectorInvalidError
     * 
     * @return string[]
     */
    protected function split()
    {
        if ($this->splitParts === null) {
            $this->splitParts = $this->splitAlias($this->toString());
        }
        return $this->splitParts;
    }

    /**
     * Returns an array with alias parts (basically explode('.', $alias)).
     *
     * @return string[]
     * 
     * @throws SelectorInvalidError
     */
    protected function splitAlias(string $string) : array
    {
        if (mb_substr($string, 0, 1) === '.' || mb_substr($string, -1) === '.') {
            throw new SelectorInvalidError('"' . $string . '" is not a valid alias selector!');
        }
        return explode(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, $string);
    }
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Selectors\AliasSelectorInterface::getVendorAlias()
     */
    public function getVendorAlias() : string
    {
        return $this->split()[0];
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\AliasSelectorInterface::getAppAlias()
     * 
     * NOTE: there is no corresponding getComponentAlias() method because in case of selectors,
     * that can be paths or classes as well as aliases, such a method would only work if the value
     * is an alias. If you know, a selector is an alias, you can get the component alias by
     * calling stripNamespace($fullAlias).
     */
    public function getAppAlias() : string
    {
        return implode(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, array_slice($this->split(), 0, 2));
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\AliasSelectorInterface::isAlias()
     */
    public function isAlias()
    {
        if (is_null($this->isAlias)) {
            try {
                $this->split();
                $this->isAlias = true;
            } catch (SelectorInvalidError $e) {
                $this->isAlias = false;
            }
        }
        return $this->isAlias;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\AliasSelectorInterface::getAppSelector()
     */
    public function getAppSelector() : AppSelectorInterface
    {
        return new AppSelector($this->getWorkbench(), $this->getAppAlias());
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\AliasSelectorWithOptionalNamespaceInterface::hasNamespace()
     */
    public function hasNamespace() : bool
    {
        return substr_count($this->toString(), AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER) >= 2;
    }
}