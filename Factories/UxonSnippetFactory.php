<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Selectors\UxonSnippetSelector;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\PhpFilePathDataType;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Uxon\UxonSnippetInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\Uxon\UxonSnippetCall;

/**
 * This factory produces UXON snippets
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class UxonSnippetFactory extends AbstractStaticFactory
{
    private static $prototypeClasses = [];

    private static $resolver = null;

    /**
     * Instantiates a new snippet for the given selector loading its contents from the model.
     * 
     * @param \exface\Core\Interfaces\WorkbenchInterface $workbench
     * @param string $aliasOrUid
     * @return \exface\Core\Interfaces\Uxon\UxonSnippetInterface
     */
    public static function createFromString(WorkbenchInterface $workbench, string $aliasOrUid) : UxonSnippetInterface
    {
        $selector = new UxonSnippetSelector($workbench, $aliasOrUid);
        $snippet = $workbench->model()->getModelLoader()->loadSnippet($selector);
        return $snippet;
    }

    /**
     * 
     * @param \exface\Core\Interfaces\WorkbenchInterface $workbench
     * @param string $prototype
     * @param string $alias
     * @param string $appSelector
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return object
     */
    public static function createFromPrototype(WorkbenchInterface $workbench, string $prototype, string $alias, string $appSelector, UxonObject $uxon) : UxonSnippetInterface
    {
        $class = self::$prototypeClasses[$prototype] ?? null;
        if ($class === null) {
            $path = FilePathDataType::isAbsolute($prototype) ? $prototype : $workbench->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $prototype;
            $class = PhpFilePathDataType::findClassInFile($path);
            self::$prototypeClasses[$prototype] = $class;
        }
        if ($class === null) {
            throw new RuntimeException('UXON snippet prototype ' . $prototype . ' not found');
        }
        $snippet = new $class($workbench, $alias, $appSelector, $uxon);
        return $snippet;
    }

    /**
     * 
     * @param \exface\Core\Interfaces\WorkbenchInterface $workbench
     * @return (callable(UxonObject ):\exface\Core\Interfaces\Contexts\ContextInterface)
     */
    public static function getSnippetResolver(WorkbenchInterface $workbench) : callable
    {
        if (self::$resolver === null) {
            self::$resolver = function ($arrayOrUxon) use ($workbench) {
                $snippetCallUxon = $arrayOrUxon instanceof UxonObject ? $arrayOrUxon : new UxonObject($arrayOrUxon);
                $snippetCall = new UxonSnippetCall($snippetCallUxon);
                $snippet = static::createFromString($workbench, $snippetCall->getSnippetAlias());
                return $snippet->render($snippetCall);
            };
        }
        return self::$resolver;
    }
}