<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\UrlDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Facades\HttpFileServerFacade;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Exceptions\FormulaError;

/**
 * Produces a download link for a file fromn the given object and UID.
 * 
 * If the fourth parameter is set to `true` the link will only work one time but 
 * without authentification.
 * 
 * Examples: 
 * 
 * - `=Permalink('exface.core.show_object', '8978')` => https://myserver.com/api/files/example.App.Image/8978
 * - `=FileLink('example.App.Image', '8978', null, true)` => https://myserver.com/api/files/otl/7ab61d32-ff38-4a71-a52a-ae61c016e613
 * 
 * 
 * @author Ralf Mulansky
 *        
 */
class Permalink extends \exface\Core\CommonLogic\Model\Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run(string $permalinkAlias = '')
    {
        if ($objectAlias === '') {
            throw new FormulaError('Can not evaluate FileLink formula: no valid object provided!');
        }
        if ($uid === '') {
            throw new FormulaError('Can not evaluate FileLink formula: no UID value provided!');
        }
        $object = MetaObjectFactory::createFromString($this->getWorkbench(), $objectAlias);

        $url = HttpFileServerFacade::buildUrlToDownloadData($object, $uid, $urlParams, true, false);
        if ($makeOneTimeLink === true) {
            $url = HttpFileServerFacade::buildUrlToOneTimeLink($this->getWorkbench(), $url, false);
        }
        return $url;
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