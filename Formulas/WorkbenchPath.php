<?php
namespace exface\Core\Formulas;


use exface\Core\Factories\UiPageFactory;
use exface\Core\Factories\SelectorFactory;
use exface\Core\DataTypes\UrlDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\FilePathDataType;

/**
 * Produces a path by adding the given string to the base workbench path.
 * 
 * E.g. 
 * - `=WorkbenchPath('data/.payloadPackages')` => C:/wamp/www/powerui/data/.payloadPackages
 *
 * @author Ralf Mulansky
 *        
 */
class WorkbenchPath extends \exface\Core\CommonLogic\Model\Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run(string $string = null, $separator = null)
    {
        $string = str_replace('/', DIRECTORY_SEPARATOR, trim($string ?? ''));
        $string = $this->getWorkbench()->filemanager()->getPathToBaseFolder() . DIRECTORY_SEPARATOR . $string;
        if ($separator) {
            $string = str_replace(DIRECTORY_SEPARATOR, $separator, $string);
        }
        return $string;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), FilePathDataType::class);
    }
}