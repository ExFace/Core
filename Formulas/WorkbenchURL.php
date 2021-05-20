<?php
namespace exface\Core\Formulas;


use exface\Core\Factories\UiPageFactory;
use exface\Core\Factories\SelectorFactory;
use exface\Core\DataTypes\UrlDataType;
use exface\Core\Factories\DataTypeFactory;

/**
 * Produces an URL by adding the given string to the base workbench url
 * 
 * E.g. 
 * - `=WorkbenchURL('api/packagist/packages')` => https://myserver.com/mypath/api/packagist/packages
 *
 * @author Ralf Mulansky
 *        
 */
class WorkbenchURL extends \exface\Core\CommonLogic\Model\Formula
{
    public function run(string $string)
    {
        return $this->getWorkbench()->getUrl() . $string;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), UrlDataType::class);
    }
}