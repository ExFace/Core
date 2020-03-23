<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Model\UiPageTreeNodeInterface;

class UiPageTree
{
    private $exface = null;
    
    private $depth = null;
    
    private $startRootNodes = [];
        
    private $rootNodes = [];
    
    private $expandPathToNode = null;
    
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
    protected function buildStartRootNodes(array $pages) : UiPageTree
    {
        foreach ($pages as $page) {
            $node = new UiPageTreeNode($this->getWorkbench(), $page->getAlias(), $page->getName(), $page->getId());
            $node->setDescription($page->getDescription());
            $node->setIntro($page->getIntro());
            $this->startRootNodes[] = $node;
        }
        return $this;
    }
    
    public function setStartRootNodes (array $nodes) : UiPageTree
    {
        if (empty($this->startRootNodes)) {
            $this->startRootNodes = $nodes;
        }
        #TODO throw Excpetion maybe
        return $this;
    }
    
    public function getStartRootNodes () : array
    {        
        return $this->startRootNodes;
    }
    
    /**
     * Set the rootPages for the tree. It is possible to have multiple pages as roots for the tree.
     * 
     * @param UiPageInterface[] $pages
     * @return UiPageTree
     */
    public function setRootPages(array $pages) : UiPageTree
    {
        $this->buildStartRootNodes($pages);
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
     * Get the expand depth of the tree
     * 
     * @return int|NULL
     */
    public function getExpandDepth() : ?int
    {
        return $this->depth;
    }
    
    /**
     * 
     * @param UiPageTreeNodeInterface $node
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
    public function hasExpandPathToPage() : bool
    {
        return !is_null($this->expandPathToPage);
    }
    
    /**
     * 
     * @return UiPageInterface|NULL
     */
    public function getExpandPathToPage() : ?UiPageInterface
    {
        return $this->expandPathToPage;
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
    public function isLoaded() : bool
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
        $this->rootNodes = $this->getWorkbench()->model()->getModelLoader()->loadUiPageTree($this);
        return $this;
    }
    
    /**
     * Checks if the given node is in the satrt root nodes of this tree.
     * 
     * @param UiPageTreeNodeInterface $node
     * @return bool
     */
    public function nodeInRootNodes(UiPageTreeNodeInterface $node) : bool
    {
        foreach ($this->startRootNodes as $rootNode) {
            if ($node->getUid() === $rootNode->getUid()) {
                return true;
            }
        }
        return false;
    }
}