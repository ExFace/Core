<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\Workbench;

class UiPageTree
{
    private $exface = null;
    
    private $depth = null;
    
    private $rootPages = [];
    
    private $rootNodes = null;
    
    private $expandPathToPage = null;
    
    private $expandPathOnly = false;
    
    public function __construct(Workbench $exface)
    {
        $this->exface = $exface;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
    }
    
    /**
     * 
     * @return UiPageTreeNode
     */
    public function getRootNodes() : array
    {
        if ($this->isLoaded() === false) {
            $this->loadTree();
        }
        return $this->rootNodes;
    }
    
    /**
     * 
     * @param UiPageInterface[] $pages
     * @return UiPageTree
     */
    public function setRootPages(array $pages) : UiPageTree
    {
        $this->rootPages = $pages;
        $this->reset();
        return $this;
    }
    
    /**
     * 
     * @param int|null $levelsToLoad
     * @return UiPageTree
     */
    public function setExpandDepth($levelsToLoad) : UiPageTree
    {
        if ($levelsToLoad !== null && is_int($levelsToLoad) === false) {
            throw new InvalidArgumentException('TODO');
        }
        $this->depth = $levelsToLoad;
        $this->reset();
        return $this;
    }
    
    public function setExpandPathToPage(UiPageInterface $page) : UiPageTree
    {
        $this->expandPathToPage = $page;
        $this->reset();
        return $this;
    }
    
    protected function hasExpandPathToPage() : bool
    {
        return !is_null($this->expandPathToPage);
    }
    
    protected function getExpandPathToPage() : ?UiPageInterface
    {
        return $this->expandPathToPage;
    }
    
    protected function isExpandPathOnly() : bool
    {
        return $this->expandPathOnly;
    }
    
    public function setExpandPathOnly(bool $trueOrFalse) : UiPageTree
    {
        $this->expandPathOnly = $trueOrFalse;
        $this->reset();
        return $this;
    }
    
    protected function reset() : UiPageTree
    {
        $this->rootNodes = null;
        return $this;
    }
    
    protected function isLoaded() : bool
    {
        return $this->rootNodes !== null;
    }
    
    protected function loadTree() : UiPageTree
    {
        if ($this->hasExpandPathToPage() && $this->expandPathOnly === false) {
            $this->rootNodes = $this->buildParentMenuNodes($this->getExpandPathToPage());
        }
        return $this;
    }
    
    protected function getMenuDataSheet(UiPageSelectorInterface $parentPageSelector) : DataSheetInterface
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.PAGE');
        $ds->getColumns()->addMultiple(['UID', 'CMS_ID', 'NAME', 'DESCRIPTION', 'INTRO', 'ALIAS']);
        $ds->getSorters()->addFromString('MENU_POSITION');
        
        if ($parentPageSelector->isAlias()) {
            $parentAlias = 'MENU_PARENT__ALIAS';
        } elseif ($parentPageSelector->isUid()) {
            $parentAlias = 'MENU_PARENT__UID';
        } elseif ($parentPageSelector->isCmsId()) {
            $parentAlias = 'MENU_PARENT';
        } else {
            throw new \ErrorException($this, "Invalid page selector '{$parentPageSelector->toString()}'");
        }
        
        $ds->addFilterFromString($parentAlias, $parentPageSelector->toString(), '==');
        $ds->addFilterFromString('MENU_VISIBLE', 1, '==');
        
        $ds->dataRead();
        return $ds;
    }
    
    protected function buildParentMenuNodes(UiPageInterface $page, string $childPageId = null, array $childNodes = []) : array
    {
        $menuNodes = [];
        $pageSelector = $page->getSelector();        
        //get all data for child pages from currentPage
        $dataSheet = $this->getMenuDataSheet($pageSelector);
        foreach ($dataSheet->getRows() as $row) {
            $node = new UiPageTreeNode($pageSelector, $row['ALIAS'], $row['NAME'], $row['CMS_ID']);
            if ($childPageId && $childPageId === $row['UID'] && $childNodes !== null) {
                foreach ($childNodes as $child) {
                    $child->setParentNode($node);
                    $node->addChildNode($child);
                }
            }
            $menuNodes[] = $node;
        }
        $pageId = $page->getId();        
        //if page has a menu parent page continue building menu by going one level up
        if ($page->getMenuParentPage() !== null) {
            $parentPage = $page->getMenuParentPage();
            $menuNodes = $this->buildParentMenuNodes($parentPage, $pageId, $menuNodes);
        }
        return $menuNodes;
    }
}