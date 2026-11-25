<?php
namespace exface\Core\Uxon;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\CommonLogic\Uxon\AbstractUxonSnippet;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\JsonDataType;
use exface\Core\DataTypes\UxonSchemaDataType;
use exface\Core\Exceptions\Selectors\SelectorInvalidError;
use exface\Core\Exceptions\Uxon\UxonSnippetNotFoundError;
use exface\Core\Factories\UxonSnippetFactory;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Uxon\UxonSnippetInterface;

/**
 * UXON-schema class for meta object behaviors.
 * 
 * @see UxonSchema for general information.
 * 
 * @author Andrej Kabachnik
 *
 */
class SnippetSchema extends UxonSchema
{
    public static function getSchemaName() : string
    {
        return UxonSchemaDataType::SNIPPET;
    }

    /**
     * Finds the enclosing snippet, allowing its alias to be incomplete while editing.
     *
     * @param UxonObject $uxon
     * @param array $path
     * @return UxonSnippetInterface|null
     */
    protected function findSnippet(UxonObject $uxon, array $path) : ?UxonSnippetInterface
    {
        $level = $uxon;
        $snippetAlias = null;
        foreach ($path as $step) {
            $value = $level->getProperty($step);
            if ($value instanceof UxonObject) {
                if ($value->hasProperty(UxonObject::PROPERTY_SNIPPET)) {
                    $snippetAlias = $value->getProperty(UxonObject::PROPERTY_SNIPPET);
                }
                $level = $value;
            } else {
                break;
            }
        }
        if ($snippetAlias !== null) {
            try {
                return UxonSnippetFactory::createFromString($this->getWorkbench(), $snippetAlias);
            } catch (UxonSnippetNotFoundError | SelectorInvalidError $e) {
                $this->handleIncompleteInputError($e);
            }
        }
        return null;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Uxon\UxonSchema::getProperties()
     */
    public function getProperties(string $prototypeClass, UxonObject $uxon, array $path) : array
    {
        $params = [];
        $lastKey = $path[count($path) - 2] ?? null;
        if ($lastKey === 'parameters') {
            $snippet = $this->findSnippet($uxon, $path);
            if ($snippet !== null) {
                foreach ($snippet->getParameters() as $param) {
                    $params[] = $param->getName();
                }
            }
        }
        return ! empty($params) ? $params : parent::getProperties($prototypeClass, $uxon, $path);
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Uxon\UxonSchema::getPropertiesTemplates()
     */
    public function getPropertiesTemplates(string $prototypeClass, UxonObject $uxon, array $path) : array
    {
        $tpls = parent::getPropertiesTemplates($prototypeClass, $uxon, $path);
        $snipCall = [];
        $snippet = $this->findSnippet($uxon, $path);
        if ($snippet !== null) {
            foreach ($snippet->getParameters() as $param) {
                $snipCall[$param->getName()] = '';
            }
        }
        if (! empty($snipCall)) {
            $tpls['parameters'] = JsonDataType::encodeJson($snipCall);
        }
        return $tpls; 
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Uxon\UxonSchema::getValidValues()
     */
    public function getValidValues(UxonObject $uxon, array $path, string $search = null, string $rootPrototypeClass = null, MetaObjectInterface $rootObject = null) : array
    {
        return parent::getValidValues($uxon, $path, $search, $rootPrototypeClass, $rootObject);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Uxon\UxonSchema::getDefaultPrototypeClass()
     */
    protected function getDefaultPrototypeClass() : string
    {
        return '\\' . AbstractUxonSnippet::class;
    }
}