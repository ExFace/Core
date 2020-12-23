<?php
namespace exface\Core\Formulas;


use exface\Core\Factories\UiPageFactory;
use exface\Core\Factories\SelectorFactory;
use exface\Core\DataTypes\UrlDataType;
use exface\Core\Factories\DataTypeFactory;

/**
 * Produces an URL for a given page selector
 * 
 * E.g. 
 * - `=PageURL('exface.core.administration')` => https://myserver.com/mypath/exface.core.administration.html
 * - `=PageURL('0xf8310d4bba2c11e7895fe4b318306b9a')` => https://myserver.com/mypath/exface.core.administration.html
 *
 * @author Andrej Kabachnik
 *        
 */
class PageURL extends \exface\Core\CommonLogic\Model\Formula
{
    public function run(string $pageSelectorString)
    {
        $selector = SelectorFactory::createPageSelector($this->getWorkbench(), $pageSelectorString);
        if ($selector->isAlias()) {
            $alias = $selector;
        } else {
            $page = UiPageFactory::createFromModel($this->getWorkbench(), $pageSelectorString);
            $alias = $page->getAliasWithNamespace();
        }
        return $this->getWorkbench()->getUrl() . $alias . '.html';
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