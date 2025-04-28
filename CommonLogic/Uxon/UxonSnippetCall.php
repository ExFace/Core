<?php
namespace exface\Core\CommonLogic\Uxon;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Uxon\UxonSnippetCallInterface;
use exface\Core\Uxon\SnippetSchema;

class UxonSnippetCall implements UxonSnippetCallInterface
{
    use ImportUxonObjectTrait;

    private $uxon = null;

    public function __construct(UxonObject $uxon)
    {
        $this->uxon = $uxon;
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
    public function getParameters() : array
    {
        $paramsUxon = $this->uxon->getProperty('parameters');
        return $paramsUxon === null ? [] : $paramsUxon->toArray();
    }

    /**
     * Alias of the snippet to call
     * 
     * @uxon-property ~snippet
     * @uxon-type metamodel:snippet
     * @uxon-required true
     * 
     * @return string
     */
    public function getSnippetAlias() : string
    {
        return $this->uxon->getProperty(UxonObject::PROPERTY_SNIPPET);
    }

    public function exportUxonObject()
    {
        return $this->uxon;
    }    

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass() : ?string
    {
        return SnippetSchema::class;
    }
}