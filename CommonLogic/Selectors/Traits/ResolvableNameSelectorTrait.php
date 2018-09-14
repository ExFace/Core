<?php
namespace exface\Core\CommonLogic\Selectors\Traits;

use exface\Core\Exceptions\Selectors\SelectorInvalidError;
use exface\Core\Interfaces\Selectors\FileSelectorInterface;
use exface\Core\Interfaces\Selectors\ClassSelectorInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\CommonLogic\Filemanager;

/**
 * Resolvable names are qualified class names, file paths (absolute or relative to
 * the vendor folder) and qualified aliases (with namespaces).
 *
 * @author Andrej Kabachnik
 *
 */
trait ResolvableNameSelectorTrait
{
    use AliasSelectorTrait {
        getAppAlias as getAppAliasFromAlias;
    }
    use PrototypeSelectorTrait;
    
    private $splitParts = null;
    
    protected function split() : array
    {
        if ($this->splitParts === null) {
            $string = $this->toString();
            switch (true) {
                case $this->isAlias(): 
                    $this->splitParts = explode(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, $string);
                    break;
                case $this->isFilepath():
                    $string = $this->getPathRelativeToVendorFolder();
                    $string = $this::stripExtension($string, FileSelectorInterface::PHP_FILE_EXTENSION);
                    $this->splitParts = explode(FileSelectorInterface::NORMALIZED_DIRECTORY_SEPARATOR, Filemanager::pathNormalize($string, FileSelectorInterface::NORMALIZED_DIRECTORY_SEPARATOR));
                    break;
                case $this->isClassname():
                    $this->splitParts = explode(ClassSelectorInterface::CLASS_NAMESPACE_SEPARATOR, $string);
                    break;
                default:
                    throw new SelectorInvalidError($this->toString() . ' is not a resolvable name: expecting model alias, qualified class name or file path (absolute or relative to vendor folder).');
            }
        }
        return $this->splitParts;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\AliasSelectorInterface::isAlias()
     */
    public function isAlias()
    {
        return (! $this->isFilepath() && ! $this->isClassname());
    }
    
    public function getAppAlias() : string
    {
        if ($this->isAlias()) {
            $this->isFilepath();
            return $this->getAppAliasFromAlias();
        } 
        return $this->getPrototypeAppAlias();
    }
}