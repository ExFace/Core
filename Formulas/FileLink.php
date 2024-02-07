<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\UrlDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Facades\HttpFileServerFacade;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Exceptions\FormulaError;

/**
 * Produces a a link for a file fromn the given object and uid.
 * Opening that link will load the file with the given properties from the server.
 * If the fourth parameter is set to `true` the link will only work one time but without authentification.
 * 
 * E.g. 
 * - `=FileLink('example.App.Image', '1', 'resize=300x180', true)` => https://myserver.com/mypath/api/files/otl/1234567890ACBDFE
 *
 * @author Ralf Mulansky
 *        
 */
class FileLink extends \exface\Core\CommonLogic\Model\Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run(string $objectAlias = '', string $uid = '', string $properties = null, bool $makeOneTimeLink = false)
    {
        if ($objectAlias === '') {
            throw new FormulaError('Can not evaluate OneTimeLink formula. Object alias with namespace is needed!');
        }
        if ($uid === '') {
            throw new FormulaError('Can not evaluate OneTimeLink formula. Uid is needed!');
        }
        $object = MetaObjectFactory::createFromString($this->getWorkbench(), $objectAlias);

        if ($makeOneTimeLink) {
            return HttpFileServerFacade::buildUrlToOneTimeLink($object, $uid, $properties, false);
        }
        return HttpFileServerFacade::buildUrlToDownloadData($object, $uid, $properties, true, false);
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