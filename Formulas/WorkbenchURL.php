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
 * - `=WorkbenchURL('api/packagist')` => https://myserver.com/mypath/api/packagist
 *
 * @author Ralf Mulansky
 *        
 */
class WorkbenchURL extends \exface\Core\CommonLogic\Model\Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run(string $string = null)
    {
        return $this->getWorkbench()->getUrl() . trim($string ?? '');
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