<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\CmsConnectorInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Exceptions\UiPageNotFoundError;

abstract class AbstractCmsConnector implements CmsConnectorInterface
{
    
    /**
     * Page cache [ cmsId => UiPage ]
     * @var array
     */
    protected $pageCacheByCmsId = [];
    
    /**
     * Replacing pages [ replacedPageCmsId => replacingPageCmsId ]
     * @var array
     */
    protected $pageCacheReplacements = [];

    protected $defaultPage = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::loadPage()
     */
    public function loadPage($page_id_or_alias, $ignore_replacements = false)
    {
        if (! $page_id_or_alias) {
            return $this->getDefaultPage();
        }
        
        if (! $page = $this->getPageFromCache($page_id_or_alias)) {
            if ($this->isUid($page_id_or_alias)) {
                $page = $this->getPageFromCms(null, $page_id_or_alias, null, $ignore_replacements);
            } elseif ($this->isAlias($page_id_or_alias)) {
                $page = $this->getPageFromCms(null, null, $page_id_or_alias, $ignore_replacements);
            } else {
                $page = $this->getPageFromCms($page_id_or_alias, null, null, $ignore_replacements);
            }
        }
        
        return $page;
    }
    
    /**
     * Returns the requested UiPage from the CMS.
     * 
     * @param integer $cmsId
     * @param string $uid
     * @param string $alias
     * @param boolean $ignore_replacements
     * @return UiPageInterface
     */
    abstract protected function getPageFromCms($cmsId = null, $uid = null, $alias = null, $ignore_replacements = false);
    
    /**
     * Returns a UiPage from the page cache or false if it is not found in the cache.
     * 
     * @param string $id_or_alias
     * @return UiPageInterface|boolean
     */
    protected function getPageFromCache($id_or_alias) {
        // No empty keys in cache allowed!
        if (is_null($id_or_alias) || $id_or_alias === '') {
            return false;
        }
        
        if (! $this->isCmsId($id_or_alias)) {
            // Wurde keine CMS-ID uebergeben wird der Cache nach passenden Aliasen und UIDs durchsucht
            // und die uebergebene ID durch die CMS-ID ersetzt wenn eine passende Seite gefunden wird.
            foreach ($this->pageCacheByCmsId as $idCms => $page) {
                if ($page->getAliasWithNamespace() === $id_or_alias || $page->getId() === $id_or_alias) {
                    $id_or_alias = $idCms;
                    break;
                }
            }
        }
        
        // Return direct hits right away!
        if ($this->isCmsId($id_or_alias)) {
            if (array_key_exists($id_or_alias, $this->pageCacheReplacements) && array_key_exists($this->pageCacheReplacements[$id_or_alias], $this->pageCacheByCmsId)) {
                return $this->pageCacheByCmsId[$this->pageCacheReplacements[$id_or_alias]];
            } elseif (array_key_exists($id_or_alias, $this->pageCacheByCmsId)) {
                return $this->pageCacheByCmsId[$id_or_alias];
            }
        }
        
        return false;
    }
    
    /**
     * Replaces the $originalPage in the page cache by the replacing page $replacementPage.
     * 
     * $cmsIdReplacement is the CMS-ID of the replacing page. The next time the
     * originalPage is requested from the page cache the replacing page is returned.
     * 
     * @param UiPageInterface $originalPage
     * @param integer $cmsIdReplacement
     * @param UiPageInterface $replacementPage
     * @return AbstractCmsConnector
     */
    protected function replacePageInCache(UiPageInterface $originalPage, $cmsIdReplacement, UiPageInterface $replacementPage)
    {
        if ($originalCmsId = $this->getCachedPageCmsId($originalPage)) {
            // vorhanden: 2 => 3, hinzufuegen: 1 => 2, tatsaechlich hinzuguegen: 1 => 3
            // (zu lesen: Seite 2 wird ersetzt durch Seite 3, ...)
            if (array_key_exists($cmsIdReplacement, $this->pageCacheReplacements)) {
                $cmsIdReplacement = $this->pageCacheReplacements[$cmsIdReplacement];
            }
            $this->pageCacheReplacements[$originalCmsId] = $cmsIdReplacement;
            
            // vorhanden: 2 => 3, hinzufuegen: 3 => 4, vorhandenes aendern: 2 => 4
            $this->pageCacheReplacements = array_replace($this->pageCacheReplacements, array_fill_keys(array_keys($this->pageCacheReplacements, $originalCmsId), $cmsIdReplacement));
        }
        $this->addPageToCache($cmsIdReplacement, $replacementPage);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::getPageIdInCms()
     */
    public function getPageIdInCms(UiPageInterface $page) {
        if (! is_null($cmsId = $this->getCachedPageCmsId($page))) {
            return $cmsId;
        } else {
            $this->loadPage($page->getAliasWithNamespace());
            if (! is_null($cmsId = $this->getCachedPageCmsId($page))) {
                return $cmsId;
            } else {
                throw new UiPageNotFoundError('The UiPage "' . $page->getAliasWithNamespace() . '" doesn\'t exist.');
            }
        }
    }
    
    /**
     * Searches for the passed UiPage in the page cache and returns its CMS-ID if it is
     * found, null otherwise.
     * 
     * @param UiPageInterface $page
     * @return integer|null
     */
    protected function getCachedPageCmsId(UiPageInterface $page)
    {
        foreach ($this->pageCacheByCmsId as $cmsId => $cachedPage) {
            if ($page->isExactly($cachedPage)) {
                return $cmsId;
            }
        }
        return null;
    }

    /**
     * Adds the passed UiPage to the page cache.
     * 
     * @param UiPageInterface $page
     * @return CmsConnectorInterface
     */
    protected function addPageToCache($cmsId, UiPageInterface $page)
    {
        $this->pageCacheByCmsId[$cmsId] = $page;
        return $this;
    }
    
    /**
     * Clears the page cache.
     * 
     * @return AbstractCmsConnector
     */
    protected function clearPagesCache()
    {
        $this->pageCacheByCmsId = [];
        $this->pageCacheReplacements = [];
        return $this;
    }

    /**
     * Returns a default page.
     * 
     * @return UiPageInterface
     */
    protected function getDefaultPage()
    {
        if (! $this->defaultPage) {
            $this->defaultPage = UiPageFactory::createEmpty($this->getWorkbench()->ui());
        }
        return $this->defaultPage;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::savePage()
     */
    public function savePage(UiPageInterface $page)
    {
        if ($this->hasPage($page)) {
            $this->updatePage($page);
        } else {
            $this->createPage($page);
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::hasPage()
     */
    public function hasPage($page_or_id_or_alias)
    {
        $page_identifier = $page_or_id_or_alias instanceof UiPageInterface ? $page_or_id_or_alias->getAliasWithNamespace() : $page_or_id_or_alias;
        
        if ($this->getPageFromCache($page_identifier)) {
            return true;
        } else {
            try {
                $this->getPageId($page_identifier);
                return true;
            } catch (UiPageNotFoundError $upnfe) {
                return false;
            }
        }
        return false;
    }
    
    /**
     * Returns if the passed $page_id_or_alias is an UID.
     * 
     * @param string $page_id_or_alias
     * @return boolean
     */
    protected function isUid($page_id_or_alias)
    {
        if (substr($page_id_or_alias, 0, 2) == '0x') {
            return true;
        } 
        return false;
    }
    
    /**
     * Returns if the passed $page_id_or_alias is an alias.
     * 
     * @param string $page_id_or_alias
     * @return boolean
     */
    protected function isAlias($page_id_or_alias)
    {
        if (! $this->isUid($page_id_or_alias) && ! is_numeric($page_id_or_alias)) {
            return true;
        }
        return false;
    }
    
    /**
     * Returns if the passed $page_id_or_alias is a CMS-ID.
     * 
     * @param string $page_id_or_alias
     * @return boolean
     */
    abstract protected function isCmsId($page_id_or_alias);
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::getPageIdRoot()
     */
    public function getPageIdRoot()
    {
        return $this->getApp()->getConfig()->getOption('MODX.PAGES.ROOT_CONTAINER_ID');
    }
}