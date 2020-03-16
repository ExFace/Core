<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\SelectorFactory;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Model\UiPageTreeNodeInterface;

class UiPageTree
{
    private $exface = null;
    
    private $depth = null;
    
    private $rootPagesNodes = [];
        
    private $rootNodes = [];
    
    private $expandPathToNode = null;
    
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
     * 
     * @param UiPageInterface[] $pages
     * @return UiPageTree
     */
    protected function setRootPagesNodes(array $pages) : UiPageTree
    {
        foreach ($pages as $page) {
            $node = new UiPageTreeNode($this->getWorkbench(), $page->getAlias(), $page->getName(), $page->getId());
            $node->setDescription($page->getDescription());
            $node->setIntro($page->getIntro());
            $this->rootPagesNodes[] = $node;
        }
        return $this;
    }
    
    /**
     * Set the rootPages for the tree. It is possible to have multiple pages as roots for the tree.
     * 
     * @param UiPageInterface[] $pages
     * @return UiPageTree
     */
    public function setRootPages(array $pages) : UiPageTree
    {
        $this->setRootPagesNodes($pages);
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
     * 
     * @param UiPageTreeNodeInterface $node
     * @return UiPageTree
     */
    public function setExpandPathToNode(UiPageTreeNodeInterface $node) : UiPageTree
    {
        $this->expandPathToNode = $node;
        $this->reset();
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasExpandPathToNode() : bool
    {
        return !is_null($this->expandPathToNode);
    }
    
    /**
     * 
     * @return UiPageTreeNodeInterface|NULL
     */
    protected function getExpandPathToNode() : ?UiPageTreeNodeInterface
    {
        return $this->expandPathToNode;
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
     * property `expandPathToNode` of the tree. 
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
        if (empty($this->rootPagesNodes)) {
            $modelLoader = $this->getWorkbench()->model()->getModelLoader();
            $this->rootPagesNodes = $modelLoader->loadUiPageTreeRootNodes();
        }
        if ($this->hasExpandPathToNode()) {
            $this->rootNodes = $this->buildParentMenuNodes($this->getExpandPathToNode(), true);
        } else {
            foreach ($this->rootPagesNodes as $rootNode) {
                $this->buildChildMenuNodes(1, $rootNode);
                $this->rootNodes[] = $rootNode;
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
        } else {
            throw new \ErrorException($this, "Invalid page selector '{$parentPageSelector->toString()}'");
        }
        
        $ds->getFilters()->addConditionFromString($parentAlias, $parentPageSelector->toString(), '==');
        $ds->getFilters()->addConditionFromString('MENU_VISIBLE', 1, '==');
        
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
    protected function buildParentMenuNodes(UiPageTreeNodeInterface $node, bool $start = false) : array
    {
        $modelLoader = $this->getWorkbench()->model()->getModelLoader();
        /*@var UiPageTreeNodeInterface $node*/
        if ($start === true && $this->expandPathOnly === false) {
            $node = $modelLoader->loadUiPageTreeChildNodes($node);
        }
        $parentNode = $modelLoader->loadUiPageTreeParentNode($node, !($this->expandPathOnly));
        
        //if node is not an array and if it is not in rootPageNodes
        if ($parentNode !== null && $this->nodeInRootNodes($parentNode) === false) {
            $menuNodes = $this->buildParentMenuNodes($parentNode);
            return $menuNodes;
        }
        if ($parentNode === null) {
            $parentNode = $node;
        }
        if ($this->nodeInRootNodes($parentNode) === true) {
            for ($i = 0; $i < count($this->rootPagesNodes); $i++) {
                if ($parentNode->getUid() === $this->rootPagesNodes[$i]->getUid()) {
                    $this->rootPagesNodes[$i] = $parentNode;
                    continue;
                }
            }
            return $this->rootPagesNodes;
        } else {
            return [];
        }
    }
    
    
    protected function buildChildMenuNodes(int $level, UiPageTreeNodeInterface $node) : UiPageTreeNodeInterface
    {
        $modelLoader = $this->getWorkbench()->model()->getModelLoader();
        $modelLoader->loadUiPageTreeChildNodes($node);
        if ($this->depth === null || $level < $this->depth) {
            foreach ($node->getChildNodes() as $childNode) {
                $this->buildChildMenuNodes($level + 1, $childNode);
            }
        }
        
        return $node;
    }
    
    protected function nodeInRootNodes(UiPageTreeNodeInterface $node) : bool
    {
        foreach ($this->rootPagesNodes as $rootNode) {
            if ($node->getUid() === $rootNode->getUid()) {
                return true;
            }
        }
        return false;
    }
}