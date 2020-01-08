<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\SelectorFactory;

class UiPageTreeNode
{
    private $exface = null;
    
    private $tree = null;
    
    private $name = null;
    
    private $parentNode = null;
    
    private $pageSelector = null;
    
    private $childNodes = [];
    
    private $description = null;
    
    private $intro = null;
    
    private $cmsId = null;
    
    private $pageAlias = null;    
    
    
    public function __construct(WorkbenchInterface $exface, string $pageAlias, string $name, string $cmsId, UiPageTreeNode $parentNode = null)
    {
        $this->exface = $exface;
        $this->pageSelector = SelectorFactory::createPageSelector($exface, $pageAlias);
        $this->pageAlias = $pageAlias;
        $this->name = $name;
        $this->cmsId = $cmsId;
        if ($parentNode !== null) {
            $this->parentNode = $parentNode;
        }
        
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
    
    public function getName() : string
    {
        return $this->name;
    }
    
    public function getPageAlias() : string
    {
        return $this->pageAlias;
    }
    
    public function getCmsId() : string
    {
        return $this->cmsId;   
    }
    
    public function setParentNode(UiPageTreeNode $parentNode) : UiPageTreeNode
    {
        $this->parentNode = $parentNode;
        return $this;
    }
    
    public function hasParentNode() : bool
    {
        return $this->parentNode !== null;
    }
    
    public function getParentNode() : ?UiPageTreeNode
    {
        return $this->parentNode;   
    }
    
    public function getPage() : UiPageInterface
    {
        return UiPageFactory::create($this->getPageSelector());
    }
    
    public function getPageSelector() : UiPageSelectorInterface
    {
        return $this->pageSelector;
    }
    
    public function setIntro (string $intro) : UiPageTreeNode
    {
        $this->intro = $intro;
        return $this;
    }
    
    public function hasIntro() : bool
    {
        return $this->intro !== null;
    }
    
    public function getIntro() : string
    {
        if ($this->intro !== null) {
            return $this->intro;
        }
        return '';
    }
    
    public function setDescription (string $descpription) : UiPageTreeNode
    {
        $this->description = $descpription;
        return $this;
    }
    
    public function getDescription() : string
    {
        if ($this->description !== null) {
            return $this->description;
        }
        return '';
    }
    
    public function hasChildNodes() : bool
    {
        return empty($this->childNodes) === false;
    }
    
    public function getChildNodes() : array
    {
        return $this->childNodes;
    }
    
    public function addChildNode(UiPageTreeNode $node, int $position = null) : UiPageTreeNode
    {
        if ($node->getParentNode() !== $this){
            throw new InvalidArgumentException('TODO parent mismatch!');
        }
        
        if ($position === null || ! is_numeric($position)) {
            $this->childNodes[] = $node;
        } else {
            array_splice($this->childNodes, $position, 0, [$node]);
        }
        
        return $this;
    }
    
    public function isPage(UiPageInterface $page) : bool
    {
        return $page->is($this->getPageSelector());
    }
    
    public function isParentOf(UiPageInterface $page) : bool
    {
        if ($page->getMenuParentPage() === null) {
            return false;
        }
        return $page->getMenuParentPage()->is($this->getPageSelector());        
    }
    
    public function isAncestorOf(UiPageInterface $page) : bool
    {
        $checkPage = $page;
        while ($checkPage->getMenuParentPage() !== null) {
            if ($this->isParentOf($checkPage)) {
                return true;
            }
            $checkPage = $checkPage->getMenuParentPage();            
        }
        return false;          
    }
    
    /**
     * @return UiPageTreeNode[]
     */
    public function getAncestorNodes() : array
    {
        $node = $this;
        $ancestorNodes = [];
        while ($node->hasParentNode()) {            
            $node = $node->getParentNode();
            $ancestorNodes[] = $node;
        }
        return $ancestorNodes;
    }
}