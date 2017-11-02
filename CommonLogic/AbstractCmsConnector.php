<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\CmsConnectorInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Exceptions\UiPageNotFoundError;

abstract class AbstractCmsConnector implements CmsConnectorInterface
{

    protected $pageCache = [];

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
        
        if (substr($page_id_or_alias, 0, 2) == '0x') {
            // UID uebergeben.
            if ($page = $this->getPageFromCacheById($page_id_or_alias)) {
                return $page;
            } else {
                $page = $this->loadPageById($page_id_or_alias, $ignore_replacements, $replace_ids);
                $this->addPageToCache($page);
                if ($page->getId() != $page_id_or_alias) {
                    $replacedPage = $page->copy($this->getPageAlias($page_id_or_alias), $page_id_or_alias);
                    $this->addPageToCache($replacedPage);
                }
                return $page;
            }
        } elseif (! is_numeric($page_id_or_alias)) {
            // Alias uebergeben.
            if ($page = $this->getPageFromCacheByAlias($page_id_or_alias)) {
                return $page;
            } else {
                $page = $this->loadPageByAlias($page_id_or_alias, $ignore_replacements, $replace_ids);
                $this->addPageToCache($page);
                if ($page->getAliasWithNamespace() != $page_id_or_alias) {
                    $replacedPage = $page->copy($page_id_or_alias, $this->getPageId($page_id_or_alias));
                    $this->addPageToCache($replacedPage);
                }
                return $page;
            }
        } else {
            // CMS ID uebergeben.
            return $this->loadPage($this->getPageId($page_id_or_alias), $ignore_replacements);
        }
    }
    
    /**
     * Returns the page UID for the given UiPage or UID or alias or CMS ID.
     *
     * @param UiPageInterface|string $page_or_id_or_alias
     *
     * @throws UiPageNotFoundError
     *
     * @return string
     */
    abstract protected function getPageId($page_or_id_or_alias);
    
    /**
     * Returns the page alias for the given UiPage or UID or alias or CMS ID.
     *
     * @param UiPageInterface|string $page_or_id_or_alias
     *
     * @throws UiPageNotFoundError
     *
     * @return string
     */
    abstract protected function getPageAlias($page_or_id_or_alias);

    /**
     * Tries to retrieve a UiPage from the page cache by its UID.
     * 
     * @param string $uid
     * @return UiPageInterface|null
     */
    protected function getPageFromCacheById($uid)
    {
        foreach ($this->pageCache as $cachePage) {
            if ($uid == $cachePage->getId()) {
                return $cachePage;
            }
        }
        return null;
    }

    /**
     * Tries to retrieve a UiPage from the page cache by its alias.
     * 
     * @param string $alias_with_namespace
     * @return UiPageInterface|null
     */
    protected function getPageFromCacheByAlias($alias_with_namespace)
    {
        foreach ($this->pageCache as $cachePage) {
            if ($alias_with_namespace == $cachePage->getAliasWithNamespace()) {
                return $cachePage;
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
    protected function addPageToCache(UiPageInterface $page)
    {
        $this->pageCache[] = $page;
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
        $id_or_alias = $page_or_id_or_alias instanceof UiPageInterface ? $page_or_id_or_alias->getId() : $page_or_id_or_alias;
        try {
            $this->getPageId($id_or_alias);
            return true;
        } catch (UiPageNotFoundError $upnfe) {
            return false;
        }
    }
}