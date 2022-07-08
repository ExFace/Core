<?php
namespace exface\Core\Formulas;


use exface\Core\Factories\UiPageFactory;
use exface\Core\Factories\SelectorFactory;
use exface\Core\DataTypes\UrlDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Facades\HttpFileServerFacade;
use exface\Core\Facades\AbstractHttpFacade\Middleware\OneTimeLinkMiddleware;

/**
 * Produces a OneTimeLink for a file fromn the given object and uid.
 * 
 * E.g. 
 * - `=WorkbenchURL('api/packagist')` => https://myserver.com/mypath/api/packagist
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
    public function run(string $objectAlias = '', string $id = '', string $properties = null)
    {
        $exface = $this->getWorkbench();
        $cacheName = OneTimeLinkMiddleware::OTL_CACHE_NAME;
        if ($exface->getCache()->hasPool($cacheName)) {
            $cache = $exface->getCache()->getPool($cacheName, false);
        } else {
            $cache = $exface->getCache()->createDefaultPool($exface, $cacheName, false);
            $exface->getCache()->addPool($cacheName, $cache);
        }
        
        do {
            $rand = StringDataType::random(16);
        } while ($cache->hasItem($rand));
        
        $data = [];
        $data['object_alias'] = $objectAlias;
        $data['uid'] = $id;
        $params = [];
        if ($properties) {            
            parse_str($properties, $params);
        }
        $data['params'] = $params;
        
        $cacheItem = $cache->getItem($rand);
        $cacheItem->set($data);
        $cache->save($cacheItem);
        
        return $this->getWorkbench()->getUrl() . 'api/files' . '/'. OneTimeLinkMiddleware::OTL_FLAG . '/' . $rand;
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