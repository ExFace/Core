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
    public function loadPage($page_id_or_alias, $ignore_replacements = false, $replace_ids = [])
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
     * 
     * @param integer $cmsId
     * @param string $uid
     * @param string $alias
     * @param boolean $ignore_replacements
     * @return UiPageInterface
     */
    abstract protected function getPageFromCms($cmsId = null, $uid = null, $alias = null, $ignore_replacements = false);
    
    protected function getPageFromCache($id_or_alias) {
        // No empty keys in cache allowed!
        if (is_null($id_or_alias) || $id_or_alias === '') {
            return false;
        }
        
        // Return direkt hits right away!
        if ($this->isCmsId($id_or_alias)) {
            if ($page = $this->pageCacheByCmsId[$id_or_alias]) {
                if (array_key_exists($id_or_alias, $this->pageCacheReplacements)) {
                    return $this->pageCacheByCmsId[$this->pageCacheReplacements[$id_or_alias]];
                } else {
                    return $page;
                }
            }
        }
        
        foreach ($this->pageCacheByCmsId as $page) {
            if ($page->getAliasWithNamespace() === $id_or_alias || $page->getId() === $id_or_alias) {
                return $page;
            }
        }
        return false;
    }
    
    protected function replacePageInCache(UiPageInterface $originalPage, $cmsIdReplacement, UiPageInterface $replacementPage)
    {
        if ($originalCmsId = array_search($originalPage, $this->pageCacheByCmsId)) {
            $this->pageCacheReplacements[$originalCmsId] = $cmsIdReplacement;
            foreach (array_keys($this->pageCacheReplacements, $originalCmsId) as $recursivelyReplacedId) {
                $this->pageCacheReplacements[$recursivelyReplacedId] = $cmsIdReplacement;
            }
        } 
        $this->addPageToCache($cmsIdReplacement, $replacementPage);
        return $this;
    }
    
    public function getPageIdInCms(UiPageInterface $page) {
        if ($cmsId = array_search($page, $this->pageCacheByCmsId)) {
            return $cmsId;
        } else {
            $this->loadPage($page->getAliasWithNamespace());
        }
        return array_search($page, $this->pageCacheByCmsId);
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
    
    protected function isUid($page_id_or_alias)
    {
        if (substr($page_id_or_alias, 0, 2) == '0x') {
            return true;
        } 
        return false;
    }
    
    protected function isAlias($page_id_or_alias)
    {
        if (! $this->isUid($page_id_or_alias) && ! is_numeric($page_id_or_alias)) {
            return true;
        }
        return false;
    }
    
    abstract protected function isCmsId($page_id_or_alias);
}