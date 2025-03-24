<?php
namespace exface\Core\CommonLogic\Uxon;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\Uxon\UxonSnippetRenderingError;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Interfaces\Uxon\UxonSnippetCallInterface;
use exface\Core\Interfaces\Uxon\UxonSnippetInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Uxon\SnippetSchema;
use Throwable;

abstract class AbstractUxonSnippet implements UxonSnippetInterface
{
    use ImportUxonObjectTrait;

    private $workbench = null;
    private $uxon = null;
    private $alias = null;
    private $aliasWithNamespace = null;
    private $appSelectorString = null;
    private $name = null;
    private $objectAlias = null;
    private $object = null;
    private $forUseInPrototype = null;
    private $forUseInSchema = null;
    private $snippetUxon = null;
    private $snippetString = null;
    private $parameters = null;

    public function __construct(WorkbenchInterface $workbench, string $alias, string $appSelector, UxonObject $uxon)
    {
        $this->workbench = $workbench;
        $this->uxon = $uxon;
        $this->importUxonObject($uxon); 
        $this->alias = $alias;
        $this->appSelectorString = $appSelector;
    }

    /**
     * The snippet (template)
     * 
     * @uxon-property snippet
     * @uxon-type object
     * @uxon-required true
     * @uxon-template {"": ""}
     * 
     * @param string|\exface\Core\CommonLogic\UxonObject $uxon
     * @return UxonSnippetinterface
     */
    protected function setSnippet($uxonOrString) : UxonSnippetInterface
    {
        $this->snippetUxon = null;
        $this->snippetString = null;
        switch (true) {
            case $uxonOrString instanceof UxonObject:
                $this->snippetUxon = $uxonOrString;
                break;
            case is_string($uxonOrString): 
                $this->snippetString = $uxonOrString;
                break;
            default:
                throw new InvalidArgumentException('Invalid snippet syntax!');
        }
        return $this;
    }

    protected function getSnippetUxon() : UxonObject
    {
        if ($this->snippetUxon === null) {
            if ($this->snippetString === null) {
                throw new InvalidArgumentException('Snippet not set!');
            }
            $this->snippetUxon = UxonObject::fromJson($this->snippetString);
        }
        return $this->snippetUxon;
    }

    protected function getSnippetString() : string
    {
        if ($this->snippetString === null) {
            if ($this->snippetUxon === null) {
                throw new InvalidArgumentException('Snippet not set!');
            }
            $this->snippetString = $this->snippetUxon->toJson();
        }
        return $this->snippetString;
    }

    public function exportUxonObject()
    {
        return $this->uxon;
    }

    public function getWorkbench(): WorkbenchInterface
    {
        return $this->workbench;
    }

    public function getAlias()
    {
        return $this->alias;
    }
    
    /**
     * Alias of the snippet with app namespace
     * 
     * @uxon-property alias
     * @uxon-type metamodel:snippet
     * @uxon-required true
     *
     * @return string
     */
    public function getAliasWithNamespace()
    {
        if ($this->aliasWithNamespace === null) {
            $this->aliasWithNamespace = $this->appSelectorString . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $this->alias;
        }
        return $this->aliasWithNamespace;
    }
    
    /**
     * Returns the namespace of this instance (e.g. "my.app" for "my.app.my_object")
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->appSelectorString;
    }

    public function getMetaObject() : ?MetaObjectInterface
    {
        if ($this->object === null) {
            $this->object = MetaObjectFactory::createFromString($this->getWorkbench(), $this->objectAlias);
        }
        return $this->object;
    }

    /**
     * This snippet is to be used in the context of the object
     * 
     * @param string $aliasWithNamespace
     * @return UxonSnippetInterface
     */
    protected function setObjectAlias(string $aliasWithNamespace) : UxonSnippetInterface
    {
        $this->objectAlias = $aliasWithNamespace;
        $this->object = null;
        return $this;
    }

    /**
     * 
     * @param string $string
     * @return AbstractUxonSnippet
     */
    protected function setName(string $string) : UxonSnippetInterface
    {
        $this->name = $string;
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Uxon\UxonSnippetInterface::getName()
     */
    public function getName() : string
    {
        return $this->name ?? $this->getAliasWithNamespace();
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass() : ?string
    {
        return SnippetSchema::class;
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Uxon\UxonSnippetInterface::render()
     */
    public function render(UxonSnippetCallInterface $call) : UxonObject
    {
        $callParams = $call->getParameters();
        foreach ($this->getParameters() as $param){
            $paramVal = $callParams[$param->getName()] ?? null;
            try {
                $paramVal = $param->parseValue($paramVal);
            } catch (Throwable $e) {
                throw new UxonSnippetRenderingError($this->exportUxonObject(), 'Cannot render UXON snippet. ' . $e->getMessage(), null, $e);
            }
            if ($paramVal !== null && $paramVal !== ''){
                $callParams[$param->getName()] = $param->parseValue($paramVal);
            }
        }
        $json = StringDataType::replacePlaceholders($this->getSnippetString(), $callParams, false);
        $rendered = UxonObject::fromJson($json);
        return $rendered;
    }

    /**
     * 
     * @uxon-property parameters
     * @uxon-type \exface\Core\CommonLogic\Uxon\UxonSnippetParameter[]
     * @uxon-template [{"name": "", "description": "", "type": "", "required": false}]
     * 
     * @return UxonSnippetInterface
     */
    protected function setParameters(UxonObject $arrayOfParams) : UxonSnippetInterface
    {
        $this->parameters = $arrayOfParams;
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Uxon\UxonSnippetInterface::getParameters()
     */
    public function getParameters() : array
    {
        if ($this->parameters instanceof UxonObject) {
            $params = [];
            foreach ($this->parameters->getPropertiesAll() as $uxon){
                $params[] = new UxonSnippetParameter($this, $uxon); 
            }
            $this->parameters = $params;
        }
        return $this->parameters ?? [];
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Uxon\UxonSnippetInterface::hasParameters()
     */
    public function hasParameters() : bool
    {
        return empty($this->parameters === null) || (($this->parameters instanceof UxonObject) && $this->parameters->isEmpty());
    }
}