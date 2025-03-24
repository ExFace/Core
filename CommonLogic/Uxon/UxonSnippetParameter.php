<?php
namespace exface\Core\CommonLogic\Uxon;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
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
     * List of parameters to pass to the snippet
     * 
     * @uxon-property parameters
     * @uxon-type object
     * @uxon-template {"": ""}
     * 
     * @return array
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

    protected function setRequired(bool $value) : UxonSnippetParameterInterface
    {
        $this->required = $value;
        return $this;
    }

    protected function setType(string $type) : UxonSnippetParameterInterface
    {
        $this->type = $type;
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

    public function parseValue($val) : string
    {
        return $val;
    }
}