<?php
namespace exface\Core\CommonLogic\Selectors\Traits;

use exface\Core\Exceptions\Selectors\SelectorInvalidError;
use exface\Core\Interfaces\Selectors\FileSelectorInterface;
use exface\Core\Interfaces\Selectors\ClassSelectorInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;

/**
 * This trait adds the old NameResolver logic to a selector.
 * 
 * The old NameResolver translated class names, relative paths 
 * and prototype aliases to qualified PHP class names.
 *
 * @author Andrej Kabachnik
 *
 */
trait ResolvableNameSelectorTrait
{
    use FileSelectorTrait;
    use ClassSelectorTrait;
    use AliasSelectorTrait;
    use PrototypeSelectorTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\AliasSelectorInterface::isAlias()
     */
    public function isAlias()
    {
        return (! $this->isFilepath() && ! $this->isClassname());
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\AliasSelectorInterface::getAliasWithNamespace()
     */
    public function getAliasWithNamespace()
    {
        switch (true) {
            case ($this->isAlias()):
                return $this->toString();
            case ($this->isFilepath()):
            case ($this->isClassname()):
                $path = $this->getPathRelativeToVendorFolder();
                $path = str_replace($this->getPrototypeSubfolder() . FileSelectorInterface::NORMALIZED_DIRECTORY_SEPARATOR, '', $path);
                return $this::convertFilePathToAlias($path);
        }
        throw new SelectorInvalidError('Selector "' . $this->toString() . '" could not be parsed! It is neither an alias, nor a file path, nor a qualified class name.');
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\ClassSelectorInterface::getPathRelativeToVendorFolder()
     */
    public function getPathRelativeToVendorFolder()
    {
        switch (true) {
            case ($this->isFilepath()):
                return $this->toString();
            case ($this->isAlias()):
                return $this::convertAliasToFilePath($this->getAppAlias()) . FileSelectorInterface::NORMALIZED_DIRECTORY_SEPARATOR
                . $this->getPrototypeSubfolder() . ($this->getPrototypeSubfolder() ? FileSelectorInterface::NORMALIZED_DIRECTORY_SEPARATOR : '')
                . $this::convertAliasToFilePath($this->getAlias())
                . $this->getPrototypeClassnameSuffix();
            case ($this->isClassname()):
                return $this::convertClassPathToFilePath($this->toString) . '.' . FileSelectorInterface::PHP_FILE_EXTENSION;
        }
        throw new SelectorInvalidError('Selector "' . $this->toString() . '" could not be parsed! It is neither an alias, nor a file path, nor a qualified class name.');
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\ClassSelectorInterface::getClassname()
     */
    public function getClassname() : string
    {
        switch (true) {
            case ($this->isClassname()):
                return $this->toString();
            case ($this->isFilepath()):
            case ($this->isAlias()):
                return $this::convertFilePathToClassPath($this->getPathRelativeToVendorFolder());
        }
        throw new SelectorInvalidError('Selector "' . $this->toString() . '" could not be parsed! It is neither an alias, nor a file path, nor a qualified class name.');
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\PrototypeSelectorInterface::getPrototypeSubNamespace()
     */
    public function getPrototypeSubNamespace() : string
    {
        return str_replace(FileSelectorInterface::NORMALIZED_DIRECTORY_SEPARATOR, ClassSelectorInterface::CLASS_NAMESPACE_SEPARATOR, $this->getPrototypeSubfolder());
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\PrototypeSelectorInterface::getPrototypeClass()
     */
    public function getPrototypeClass() : string
    {
        return $this->getClassname();
    }
    
    protected static function convertAliasToClassPath($string)
    {
        return str_replace(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, ClassSelectorInterface::CLASS_NAMESPACE_SEPARATOR, $string);
    }
    
    protected static function convertAliasToFilePath($string)
    {
        return str_replace(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, FileSelectorInterface::NORMALIZED_DIRECTORY_SEPARATOR, $string);
    }
    
    protected static function convertClassPathToFilePath($string)
    {
        return str_replace(ClassSelectorInterface::CLASS_NAMESPACE_SEPARATOR, FileSelectorInterface::NORMALIZED_DIRECTORY_SEPARATOR, $string);
    }
    
    protected static function convertFilePathToAlias($string)
    {
        $string = static::stripExtension($string);
        return str_replace(FileSelectorInterface::NORMALIZED_DIRECTORY_SEPARATOR, AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, $string);
    }
    
    protected static function convertFilePathToClassPath($string)
    {
        $string = static::stripExtension($string);
        return str_replace(FileSelectorInterface::NORMALIZED_DIRECTORY_SEPARATOR, ClassSelectorInterface::CLASS_NAMESPACE_SEPARATOR, $string);
    }
    
    protected static function convertClassPathToAlias($string)
    {
        return str_replace(ClassSelectorInterface::CLASS_NAMESPACE_SEPARATOR, AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, $string);
    }
    
    protected static function stripExtension($string, $extension = FileSelectorInterface::PHP_FILE_EXTENSION)
    {
        $ext = strtolower(pathinfo($string, PATHINFO_EXTENSION));
        if ($ext === $extension) {
            $string = substr($string, 0, (-1 * strlen($ext) - 1));
        }
        return $string;
    }
}