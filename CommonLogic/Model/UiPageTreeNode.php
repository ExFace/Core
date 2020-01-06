<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Exceptions\InvalidArgumentException;

class UiPageTreeNode
{
    private $tree = null;
    
    private $name = null;
    
    private $parentNode = null;
    
    private $pageSelector = null;
    
    private $childNodes = [];
    
    private $description = null;
    
    private $intro = null;
    
    private $cmsId = null;
    
    private $pageAlias = null;
    
    private $expanded = null;
    
    
    
    public function __construct(UiPageSelectorInterface $pageSelector, string $pageAlias, string $name, string $cmsId, UiPageTreeNode $parentNode = null)
    {
        $this->pageSelector = $pageSelector;
        $this->pageAlias = $pageAlias;
        $this->name = $name;
        $this->cmsId = $cmsId;
        if ($parentNode !== null) {
            $this->parentNode = $parentNode;
        }
        
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
        $this->cmsId;   
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
    
    public function setExpanded (bool $trueOrFalse) : UiPageTreeNode
    {
        $this->expanded = $trueOrFalse;
    }
    
    public function isExpanded() : bool
    {
        if ($this->expanded !== null) {
            return $this->expanded;
        }
        return $this->hasChildNodes();
    }
    
    public function isPage(UiPageInterface $page) : bool
    {
        return $page->is($this->getPageSelector());
    }
    
    public function isPageParent(UiPageInterface $page) : bool
    {
        $selector = $this->getPageSelector();
        if ($page->getMenuParentPage() === null) {
            return false;
        }
        switch (true) {
            case $selector->isAlias(): return $page->getMenuParentPage()->getAliasWithNamespace() === $selector->toString();
            case $selector->isUid(): return $page->getMenuParentPage()->getId() === $selector->toString();
            case $selector->isCmsId():
                $pageSelector = $page->getMenuParentPage()->getSelector();
                $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.PAGE');
                $ds->getColumns()->addMultiple(['CMS_ID']);
                if ($pageSelector->isAlias()) {
                    $alias = 'ALIAS';
                } elseif ($pageSelector->isUid()) {
                    $alias = 'UID';
                } elseif ($pageSelector->isCmsId()) {
                    $alias = 'CMS_ID';
                }
                
                $ds->addFilterFromString($alias, $pageSelector->toString(), '==');
                $ds->dataRead();
                $row = $ds->getRow(0);
                $pageCmsId = $row['CMS_ID'];
                return $pageCmsId === $this->getCmsId();
        }
    }
    
    public function isPageAncestor(UiPageInterface $page) : bool
    {
        $checkPage = $page;
        while ($checkPage->getMenuParentPage() !== null) {
            if ($this->isPageParent($checkPage)) {
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