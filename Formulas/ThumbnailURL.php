<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\UrlDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Facades\HttpFileServerFacade;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Exceptions\FormulaError;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Generates a thumbnail URL for an image stored in an object with FileBehavior
 * 
 * Similar to `FileLink`, but specifically tailored for images.
 * 
 * E.g. 
 * - `=ThumbnailURL('example.App.Image', '893', '300')` => api/files/thumb/893x300/my.App.OBJECT_ALIAS/0x5468789
 * - `=ThumbnailURL('example.App.Image', '893', '300', false)` => https://myserver.com/api/files/thumb/893x300/my.App.OBJECT_ALIAS/0x5468789
 * - `=ThumbnailURL('example.App.Image', '893', '300', false, true)` => https://myserver.com/api/files/otl/7ab61d32-ff38-4a71-a52a-ae61c016e613
 *
 * @author Ralf Mulansky
 *        
 */
class ThumbnailURL extends \exface\Core\CommonLogic\Model\Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run(string $objectAlias = '', string $uid = '', int $width = null, int $height = null, bool $relativeToSiteRoot = true, bool $makeOneTimeLink = false)
    {
        if ($objectAlias === '') {
            throw new FormulaError('Can not evaluate ThumbnailURL formula: no valid object provided!');
        }
        if ($uid === '') {
            throw new FormulaError('Can not evaluate ThumbnailURL formula: no UID value provided!');
        }
        $object = MetaObjectFactory::createFromString($this->getWorkbench(), $objectAlias);
        
        $url = HttpFileServerFacade::buildUrlToThumbnail($object, $uid, $width, $height, true, $relativeToSiteRoot);
        if ($makeOneTimeLink === true) {
            $url = HttpFileServerFacade::buildUrlToOneTimeLink($this->getWorkbench(), $url, $relativeToSiteRoot);
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