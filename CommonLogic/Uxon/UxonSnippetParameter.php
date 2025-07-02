<?php
namespace exface\Core\CommonLogic\Uxon;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Uxon\UxonSnippetMissingParameterError;
use exface\Core\Interfaces\Uxon\UxonSnippetInterface;
use exface\Core\Interfaces\Uxon\UxonSnippetParameterInterface;

class UxonSnippetParameter implements UxonSnippetParameterInterface
{
    use ImportUxonObjectTrait;

    private $uxon = null;
    private $snippet = null;
    private $name = null;
    private $description = null;
    private $required = false;
    private $type = null;
    
    private mixed $defaultValue = null;

    /**
     * 
     * @param \exface\Core\Interfaces\Uxon\UxonSnippetInterface $snippet
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     */
    public function __construct(UxonSnippetInterface $snippet, UxonObject $uxon)
    {
        $this->snippet = $snippet;
        $this->uxon = $uxon;
        $this->importUxonObject($uxon);
    }

    /**
     * The name of this parameter. 
     * 
     * Use `[#TheNameYouChose#]` anywhere within the snippet to apply this parameter.
     * 
     * @uxon-property name
     * @uxon-type string
     * 
     * @param string $value
     * @return UxonSnippetParameterInterface
     */
    protected function setName(string $value) : UxonSnippetParameterInterface
    {
        $this->name = $value;
        return $this;
    }

    /**
     * 
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * 
     * @return UxonSnippetInterface
     */
    public function getSnippet() : UxonSnippetInterface
    {
        return $this->snippet;
    }

    /**
     * Description of the parameter
     * 
     * @uxon-property description
     * @uxon-type string
     * 
     * @param string $value
     * @return UxonSnippetParameter
     */
    protected function setDescription(string $value) : UxonSnippetParameterInterface
    {
        $this->description = $value;
        return $this;
    }

    public function isRequired() : bool
    {
        return $this->required;
    }

    /**
     * If TRUE, this parameter must be set, when using this snippet.
     *
     * @uxon-property required
     * @uxon-type bool
     */
    protected function setRequired(bool $value) : UxonSnippetParameterInterface
    {
        $this->required = $value;
        return $this;
    }

    /**
     * Set the datatype of this parameter.
     * 
     * @uxon-property type
     * @uxon-type string
     * 
     * @param string $type
     * @return UxonSnippetParameterInterface
     */
    protected function setType(string $type) : UxonSnippetParameterInterface
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return mixed
     */
    protected function getDefaultValue() : mixed
    {
        return $this->defaultValue;
    }

    /**
     * Define a default value for this parameter.
     * 
     * @uxon-property default_value
     * 
     * @param mixed $value
     * @return UxonSnippetParameterInterface
     */
    protected function setDefaultValue(mixed $value) : UxonSnippetParameterInterface
    {
        $this->defaultValue = $value;
        return $this;
    }

    protected function getType() : string
    {
        return $this->type ?? 'string';
    }

    public function exportUxonObject()
    {
        return $this->uxon;
    }

    public function parseValue($val) : ?string
    {
        if ($val === null || $val === ''){
            if ($this->isRequired()) {
                throw new UxonSnippetMissingParameterError('Missing required parameter "' . $this->getName() . '" for snippet "' . $this->getSnippet()->getAliasWithNamespace() . '"');
            } else {
                $val = $this->getDefaultValue();
            }
        }
        
        return $val;
    }
}