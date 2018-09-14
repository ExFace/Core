<?php
namespace exface\Core\CommonLogic\Selectors\Traits;

use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Exceptions\Selectors\SelectorInvalidError;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\CommonLogic\Selectors\AppSelector;

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
     * 
     * @param string $aliasWithNamespace
     * @return boolean|string
     */
    protected static function getAppAliasFromNamespace($aliasWithNamespace)
    {
        $parts = explode(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, $aliasWithNamespace);
        if (count($parts) < 3) {
            return false;
        }
        return implode(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, array_slice($parts, 0, 2));
    }
    
    /**
     * Returns an array with alias parts (basically explode('.', $alias)).
     * 
     * @throws SelectorInvalidError
     * 
     * @return string[]
     */
    protected function split()
    {
        if ($this->splitParts === null) {
            $this->splitParts = explode(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, $this->toString());
        }
        return $this->splitParts;
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
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\AliasSelectorInterface::getAppAlias()
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
        return $this->is;
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
}