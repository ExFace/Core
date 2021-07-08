<?php
namespace exface\Core\Formulas;


use exface\Core\Factories\UiPageFactory;
use exface\Core\Factories\SelectorFactory;
use exface\Core\DataTypes\UrlDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Factories\ExpressionFactory;

/**
 *  Replaces the placeholders in a given string with values from the Prefill data.
 *  For every placeholder in the string needs to be a value present in the data sheet.
 *  The placeholder is the column name in the datasheet, which is typically the attribute alias.
 *  It is also possible to use formulas as a placeholder, like `[#=WorkbenchURL('api/packagist')#] `
 *  
 *  ````
 *  Example:
 *  =ReplacePlaceholders('
 *  {
 *      "require": {
 *          "[#PACKAGE#]": "*"
 *      },
 *      "repositories": {
 *          "[#PACKAGE#]": {
 *              "type": "composer",
 *              "url": "[#=WorkbenchURL('api/packagist/packages')#]"
 *          }
 *      }
 *  }')
 *  
 *  ````
 *
 * @author Ralf Mulansky
 *        
 */
class ReplacePlaceholders extends \exface\Core\CommonLogic\Model\Formula
{
    public function run(string $string)
    {
        $ds = $this->getDataSheet();
        $idx = $this->getCurrentRowNumber();
        $phValues = $ds->getRow($idx);
        $phs = StringDataType::findPlaceholders($string);
        foreach($phs as $ph) {
            if (Expression::detectFormula($ph)) {
                $exp = ExpressionFactory::createFromString($this->getWorkbench(), $ph);
                $value = $exp->evaluate($ds, $idx);
                $phValues[$ph] = $value;
            }
        }
        $result = StringDataType::replacePlaceholders($string, $phValues);
        return $result;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), StringDataType::class);
    }
    
    public function isStatic() : bool
    {
        return false;
    }
}