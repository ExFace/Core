<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\UrlDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Facades\HttpFileServerFacade;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Exceptions\FormulaError;

/**
 * Produces a a link for a file fromn the given object and UID.
 * 
 * Opening that link will load the file with the given properties from the server.
 * 
 * If the fourth parameter is set to `true` the link will only work one time but 
 * without authentification.
 * 
 * Examples: 
 * 
 * - `=FileLink('example.App.Image', '8978')` => https://myserver.com/api/files/example.App.Image/8978
 * - `=FileLink('example.App.Image', '8978', 'resize=300x180')` => https://myserver.com/api/files/example.App.Image/8978?resize=300x180
 * - `=FileLink('example.App.Image', '8978', 'resize=300x180', true)` => https://myserver.com/api/files/otl/1234567890ACBDFE
 * 
 * There is also a comparable formula `=ThumbnailURL`, which is simpler to use for
 * thumbnails. It does not have the one-time-link feature though.
 * 
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
            throw new FormulaError('Can not evaluate FileLink formula: no valid object provided!');
        }
        if ($uid === '') {
            throw new FormulaError('Can not evaluate FileLink formula: no UID value provided!');
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