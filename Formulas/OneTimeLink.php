<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\UrlDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Facades\HttpFileServerFacade;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Exceptions\FormulaError;

/**
 * Produces a OneTimeLink for a file fromn the given object and uid.
 * Opening that link will load the file with the given properties from the server.
 * 
 * E.g. 
 * - `=OneTimeLink('example.App.Image', '1', 'resize=300x180')` => https://myserver.com/mypath/api/files/otl/1234567890ACBDFE
 *
 * @author Ralf Mulansky
 *        
 */
class OneTimeLink extends \exface\Core\CommonLogic\Model\Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run(string $objectAlias = '', string $uid = '', string $properties = null)
    {
        if ($objectAlias === '') {
            throw new FormulaError('Can not evaluate OneTimeLink formula. Object alias with namespace is needed!');
        }
        if ($uid === '') {
            throw new FormulaError('Can not evaluate OneTimeLink formula. Uid is needed!');
        }
        $object = MetaObjectFactory::createFromString($this->getWorkbench(), $objectAlias);
        
        return HttpFileServerFacade::buildUrlToOneTimeLink($object, $uid, false, $properties);
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