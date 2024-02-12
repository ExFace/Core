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
 * - `=ThumbnailURL('example.App.Image', '893', '300')` => https://myserver.com/mypath/api/files/893?resize=300x
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
    public function run(string $objectAlias = '', string $uid = '', int $width = null, int $height = null, bool $relativeToSiteRoot = true)
    {
        if ($objectAlias === '') {
            throw new FormulaError('Can not evaluate ThumbnailURL formula: no valid object provided!');
        }
        if ($uid === '') {
            throw new FormulaError('Can not evaluate ThumbnailURL formula: no UID value provided!');
        }
        $object = MetaObjectFactory::createFromString($this->getWorkbench(), $objectAlias);
        
        return self::buildThumbnailUrlForUID($object, $uid, $width, $height, $relativeToSiteRoot);
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
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @param string $uid
     * @param string $width
     * @param string $height
     * @param bool $relativeToSiteRoot
     * @return string
     */
    public static function buildThumbnailUrlForUID(MetaObjectInterface $object, string $uid, string $width = null, string $height = null, bool $relativeToSiteRoot = true) : string
    {
        $url = HttpFileServerFacade::buildUrlToDownloadData($object, $uid, null, false, $relativeToSiteRoot);
        if ($width !== null && $height !== null) {
            $url .= "?&resize={$width}x{$height}";
        }
        return $url;
    }
}