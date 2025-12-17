<?php
namespace exface\Core\CommonLogic\Traits;

use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorWithOptionalNamespaceInterface;
use exface\Core\Interfaces\Selectors\VersionedSelectorInterface;

trait AliasTrait {
    
    private $alias = null;
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\AliasInterface::getAlias()
     */
    public function getAlias()
    {
        if ($this->alias === null) {
            $class = substr(strrchr(get_class($this), '\\'), 1);
            if ($suffix = $this->getClassnameSuffixToStripFromAlias()) {
                $this->alias = substr($class, 0, (-1*strlen($suffix)));
            } else {
                $this->alias = $class;
            }
        }
        return $this->alias;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\AliasInterface::getAliasWithNamespace()
     */
    public function getAliasWithNamespace()
    {
        $selector = $this->getSelector();
        if ($selector instanceof AliasSelectorWithOptionalNamespaceInterface && ! $this->getSelector()->hasNamespace()) {
            if ($selector instanceof VersionedSelectorInterface){
                $alias = $this->getAlias();
            } else {
                $alias = $this->getSelector()->toString();
            }
        } else {
            $alias = $this->getSelector()->getAppAlias() . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $this->getAlias();
        }
        return $alias;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\AliasInterface::getNamespace()
     */
    public function getNamespace()
    {
        return $this->getSelector()->getAppAlias();
    }
    
    /**
     * 
     * @return AliasSelectorInterface
     */
    public abstract function getSelector() : AliasSelectorInterface;
    
    /**
     * 
     * @return string
     */
    protected function getClassnameSuffixToStripFromAlias() : string
    {
        return '';
    }
}