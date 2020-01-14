<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\SelectorFactory;
use exface\Core\Interfaces\WorkbenchInterface;

class UiPageTree
{
    private $exface = null;
    
    private $depth = null;
    
    private $rootPages = [];
    
    private $rootNodes = null;
    
    private $expandPathToPage = null;
    
    private $expandPathOnly = false;
    
    public function __construct(WorkbenchInterface $exface)
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
     * Returns an array containing all root nodes of the tree.
     * 
     * @return UiPageTreeNode[]
     */
    public function getRootNodes() : array
    {
        if ($this->isLoaded() === false) {
            $this->loadTree();
        }
        return $this->rootNodes;
    }
    
    /**
     * Set the rootPages for the tree. It is possible to have multiple pages as roots for the tree.
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
     * Set the depth of the tree.
     * 
     * @param int|null $levelsToLoad
     * @return UiPageTree
     */
    public function setExpandDepth($levelsToLoad) : UiPageTree
    {
        if ($levelsToLoad !== null && is_int($levelsToLoad) === false) {
            throw new InvalidArgumentException("The given value '{$levelsToLoad}' for the expand depth is not an integer!");
        }
        $this->depth = $levelsToLoad;
        $this->reset();
        return $this;
    }
    
    /**
     * Set the page the tree should expand to.
     * 
     * @param UiPageInterface $page
     * @return UiPageTree
     */
    public function setExpandPathToPage(UiPageInterface $page) : UiPageTree
    {
        $this->expandPathToPage = $page;
        $this->reset();
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasExpandPathToPage() : bool
    {
        return !is_null($this->expandPathToPage);
    }
    
    /**
     * 
     * @return UiPageInterface|NULL
     */
    protected function getExpandPathToPage() : ?UiPageInterface
    {
        return $this->expandPathToPage;
    }
    
    /**
     * 
     * @return bool
     */
    protected function isExpandPathOnly() : bool
    {
        return $this->expandPathOnly;
    }
    
    /**
     * Set if the tree should only contain nodes that are in the path to the page given in the
     * property `expandPathToPage` of the tree. 
     * 
     * @param bool $trueOrFalse
     * @return UiPageTree
     */
    public function setExpandPathOnly(bool $trueOrFalse) : UiPageTree
    {
        $this->expandPathOnly = $trueOrFalse;
        $this->reset();
        return $this;
    }
    
    /**
     * 
     * @return UiPageTree
     */
    protected function reset() : UiPageTree
    {
        $this->rootNodes = null;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function isLoaded() : bool
    {
        return $this->rootNodes !== null;
    }
    
    /**
     * Builds the nodes for the tree object depending on the properties of the tree object. 
     * 
     * @return UiPageTree
     */
    protected function loadTree() : UiPageTree
    {
        if ($this->hasExpandPathToPage()) {
            $this->rootNodes = $this->buildParentMenuNodes($this->getExpandPathToPage());
        } else {
            if (empty($this->rootPages)) {
                $pageSelector = SelectorFactory::createPageSelector($this->getWorkbench(), '1');
                $this->rootNodes = $this->buildChildMenuNodes(1, $pageSelector);
            } else {
                foreach ($this->rootPages as $rootPage) {
                    $pageSelector = $rootPage->getSelector();
                    $nodes = $this->buildChildMenuNodes(1, $pageSelector);
                    foreach ($nodes as $node) {
                        $this->rootNodes[] = $node;
                    }
                }
            }
        }
        return $this;
    }
    
    /**
     * 
     * @param UiPageSelectorInterface $parentPageSelector
     * @throws \ErrorException
     * @return DataSheetInterface
     */
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
    
    /**
     * Builds the root nodes for the tree by going levels upwards from the given `page`, till the default root page is reached or
     * an ancestor of the given `page` is in the `rootPage` array of the tree object.
     * 
     * @param UiPageInterface $page
     * @param string $childPageId
     * @param UiPageTreeNode[] $childNodes
     * @return UiPageTreeNode[]
     */
    protected function buildParentMenuNodes(UiPageInterface $page, string $childPageId = null, array $childNodes = []) : array
    {
        $menuNodes = [];
        $pageSelector = $page->getSelector();        
        //get all data for child pages from the given page
        $dataSheet = $this->getMenuDataSheet($pageSelector);
        
        //if expandPathOnly is `true` only add the node that has `childPageId` as Id.
        if ($this->expandPathOnly) {
            foreach ($dataSheet->getRows() as $row) {
                if ($childPageId && $childPageId === $row['CMS_ID']) {
                    $node = new UiPageTreeNode($this->getWorkbench(), $row['ALIAS'], $row['NAME'], $row['CMS_ID']);
                    $node->setDescription($row['DESCRIPTION']);
                    $node->setIntro($row['INTRO']);
                    //if child node array is not empty set the node as parent node for all given child nodes and the given child nodes to the node as children
                    if ($childNodes !== null) {
                        foreach ($childNodes as $child) {
                            $child->setParentNode($node);
                            $node->addChildNode($child);
                        }
                    }
                    $menuNodes[] = $node;
                }
            }
            
            //if expandPathOnly is `false|null` add all nodes that are on the same level as the page with the Id `childPageId`
        } else {
            foreach ($dataSheet->getRows() as $row) {
                $node = new UiPageTreeNode($this->getWorkbench(), $row['ALIAS'], $row['NAME'], $row['CMS_ID']);
                $node->setDescription($row['DESCRIPTION']);
                $node->setIntro($row['INTRO']);
                // when the id of the node is the same as the given `childPageId` set it as parent for all given `childNodes` and add them as child of the node
                if ($childPageId && $childPageId === $node->getCmsId()) {
                    if ($childNodes !== null) {
                        foreach ($childNodes as $child) {                    
                            $child->setParentNode($node);
                            $node->addChildNode($child);
                        }
                    }
                }
                $menuNodes[] = $node;
            }
        }
        $pageCmsId = $this->getWorkbench()->getCMS()->getPageIdInCms($page);        
        //if page has a menu parent page and if page is not in `rootPages` array continue building menu by going one level up
        if ($page->getMenuParentPage() !== null && !in_array($page, $this->rootPages)) {
            $parentPage = $page->getMenuParentPage();
            $menuNodes = $this->buildParentMenuNodes($parentPage, $pageCmsId, $menuNodes);
        }
        return $menuNodes;
    }
    
    /**
     * Builds the nodes that are children of the page inherent to the given `pageSelector`.
     * 
     * @param int $level
     * @param UiPageSelectorInterface $pageSelector
     * @param UiPageTreeNode $parentNode
     * @return UiPageTreeNode[]
     */
    protected function buildChildMenuNodes(int $level, UiPageSelectorInterface $pageSelector, UiPageTreeNode $parentNode = null) : array
    {
        $menuNodes = [];
        if ($parentNode !== null) {
            $pageSelector = $parentNode->getPageSelector();
        }
        $dataSheet = $this->getMenuDataSheet($pageSelector);
        foreach ($dataSheet->getRows() as $row) {
            $childPageSelector = SelectorFactory::createPageSelector($this->getWorkbench(), $row['CMS_ID']);
            $node = new UiPageTreeNode($this->getWorkbench(), $row['ALIAS'], $row['NAME'], $row['CMS_ID']);
            $node->setDescription($row['DESCRIPTION']);
            $node->setIntro($row['INTRO']);
            if ($parentNode) {
                $node->setParentNode($parentNode);
            }
            $menuNodes[] = $node;
            if ($this->depth === null || $level < $this->depth) {
                $childNodes = $this->buildChildMenuNodes($level + 1, $childPageSelector, $node);
                foreach ($childNodes as $childNode) {
                    $node->addChildNode($childNode);
                }
            }
        }
        return $menuNodes;
    }
}