<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\CmsConnectorInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Exceptions\UiPage\UiPageNotFoundError;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Factories\SelectorFactory;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Selectors\CmsConnectorSelectorInterface;

abstract class AbstractCmsConnector implements CmsConnectorInterface
{
    private $workbench = null;
    
    private $selector = null;
    
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
     * @param WorkbenchInterface $workbench
     */
    public function __construct(CmsConnectorSelectorInterface $selector)
    {
        $this->workbench = $selector->getWorkbench();
        $this->selector = $selector;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::getPage()
     */
    public function getPage(UiPageSelectorInterface $selector, $ignore_replacements = false) : UiPageInterface
    {        
        if (! $page = $this->getPageFromCache($selector)) {
            $page = $this->getPageFromCms($selector, $ignore_replacements);
        }
        
        return $page;
    }
    
    /**
     * Returns the requested UiPage from the CMS.
     * 
     * @param UiPageSelectorInterface $selector
     * @param boolean $ignore_replacements
     * @return UiPageInterface
     */
    abstract protected function getPageFromCms(UiPageSelectorInterface $selector, $ignore_replacements = false) : UiPageInterface;
    
    /**
     * Returns a UiPage from the page cache or false if it is not found in the cache.
     * 
     * @param string $id_or_alias
     * @return UiPageInterface|boolean
     */
    protected function getPageFromCache(UiPageSelectorInterface $selector) {        
        if (! $this->isCmsPageId($selector->toString())) {
            // Wurde keine CMS-ID uebergeben wird der Cache nach passenden Aliasen und UIDs durchsucht
            // und die uebergebene ID durch die CMS-ID ersetzt wenn eine passende Seite gefunden wird.
            foreach ($this->pageCacheByCmsId as $idCms => $page) {
                if ($page->getAliasWithNamespace() === $selector->toString() || $page->getId() === $selector->toString()) {
                    $selector = SelectorFactory::createPageSelector($this->getWorkbench(), $idCms);
                    break;
                }
            }
        }
        
        // Now, we know, that if the page is in the cache, the selector is it's UID.
        if ($this->isCmsPageId($selector->toString())) {
            $selectorString = $selector->toString();
            if (array_key_exists($selectorString, $this->pageCacheReplacements) && array_key_exists($this->pageCacheReplacements[$selectorString], $this->pageCacheByCmsId)) {
                return $this->pageCacheByCmsId[$this->pageCacheReplacements[$selectorString]];
            } elseif (array_key_exists($selectorString, $this->pageCacheByCmsId)) {
                return $this->pageCacheByCmsId[$selectorString];
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
            $this->getPage($page->getAliasWithNamespace());
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
    protected function getPageEmpty()
    {
        if (! $this->defaultPage) {
            $this->defaultPage = UiPageFactory::createEmpty($this->getWorkbench());
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
        if ($this->hasPage($page->getSelector())) {
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
    public function hasPage($selectorOrString) : bool
    {
        $selector = $selectorOrString instanceof UiPageSelectorInterface ? $selectorOrString : SelectorFactory::createPageSelector($this->getWorkbench(), $selectorString);
        
        if ($this->getPageFromCache($selector)) {
            return true;
        } else {
            try {
                $this->getPageFromCms($selector);
                return true;
            } catch (UiPageNotFoundError $upnfe) {
                return false;
            }
        }
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::getPageIdRoot()
     */
    public function getPageIdRoot()
    {
        return $this->getApp()->getConfig()->getOption('MODX.PAGES.ROOT_CONTAINER_ID');
    }
    
    
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
}