<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\SelectorFactory;
use exface\Core\Interfaces\Model\UiPageTreeNodeInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\Model\UiMenuItemInterface;
use exface\Core\CommonLogic\Traits\UiMenuItemTrait;
use exface\Core\CommonLogic\Security\Authorization\UiPageAuthorizationPoint;
use exface\Core\Exceptions\Security\AccessDeniedError;

class UiPageTreeNode implements UiPageTreeNodeInterface
{
    use UiMenuItemTrait;
    
    private $exface = null;
    
    private $tree = null;
    
    private $name = null;
    
    private $parentNode = null;
    
    private $pageSelector = null;
    
    private $childNodes = [];
    
    private $description = null;
    
    private $intro = null;
    
    private $uid = null;
    
    private $pageAlias = null;
    
    private $childNodesLoaded = false;
    
    private $published = true;
    
    
    /**
     * 
     * @param WorkbenchInterface $exface
     * @param string $pageAlias
     * @param string $name
     * @param string $uid
     * @param UiPageTreeNodeInterface $parentNode
     */
    public function __construct(WorkbenchInterface $exface, string $pageAlias, string $name, string $uid, UiPageTreeNodeInterface $parentNode = null)
    {
        $this->exface = $exface;
        $this->pageSelector = SelectorFactory::createPageSelector($exface, $pageAlias);
        $this->pageAlias = $pageAlias;
        $this->name = $name;
        $this->uid = $uid;
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
    
    /**
     * 
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }
    
    /**
     * 
     * @return string
     */
    public function getPageAlias() : string
    {
        return $this->pageAlias;
    }
    
    /**
     * 
     * @return string
     */
    public function getUid() : ?string
    {
        return $this->uid;   
    }
    
    /**
     * 
     * @param UiPageTreeNodeInterface $parentNode
     * @return UiPageTreeNodeInterface
     */
    public function setParentNode(UiPageTreeNodeInterface $parentNode) : UiPageTreeNodeInterface
    {
        $this->parentNode = $parentNode;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasParent() : bool
    {
        return $this->parentNode !== null;
    }
    
    /**
     * 
     * @return UiPageTreeNodeInterface|NULL
     */
    public function getParentNode() : ?UiPageTreeNodeInterface
    {
        return $this->parentNode;   
    }
    
    /**
     * Returns the nodes inherent page.
     * 
     * @return UiPageInterface
     */
    public function getPage() : UiPageInterface
    {
        return UiPageFactory::create($this->getPageSelector());
    }
    
    /**
     * 
     * @return UiPageSelectorInterface
     */
    public function getPageSelector() : UiPageSelectorInterface
    {
        return $this->pageSelector;
    }
    
    /**
     * 
     * @param string $intro
     * @return UiMenuItemInterface
     */
    public function setIntro (string $intro) : UiMenuItemInterface
    {
        $this->intro = $intro;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getIntro() : ?string
    {
        return $this->intro;
    }
    
    /**
     * 
     * @param string $descpription
     * @return UiMenuItemInterface
     */
    public function setDescription (string $descpription) : UiMenuItemInterface
    {
        $this->description = $descpription;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getDescription() : ?string
    {
        return $this->description;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasChildNodes() : bool
    {
        return empty($this->childNodes) === false;
    }
    
    /**
     * 
     * @return UiPageTreeNodeInterface[]
     */
    public function getChildNodes() : array
    {
        return $this->childNodes;
    }
    
    /**
     *
     * @return UiPageTreeNodeInterface
     */
    public function resetChildNodes() : UiPageTreeNodeInterface
    {
        $this->childNodes = [];
        $this->setChildNodesLoaded(false);
        return $this;
    }
    
    /**
     * 
     * @param bool $trueOrFalse
     * @return UiPageTreeNodeInterface
     */
    public function setChildNodesLoaded(bool $trueOrFalse) : UiPageTreeNodeInterface
    {
        $this->childNodesLoaded = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function getChildNodesLoaded() : bool
    {
        return $this->childNodesLoaded;
    }
    
    /**
     * 
     * @param UiPageTreeNodeInterface $node
     * @param int $position
     * @throws InvalidArgumentException
     * @return UiPageTreeNodeInterface
     */
    public function addChildNode(UiPageTreeNodeInterface $node, int $position = null) : UiPageTreeNodeInterface
    {
        if ($node->getParentNode() !== $this){
            throw new InvalidArgumentException("The parent node of the given node '{$node->getName()}' is not the node '{$this->getName()}' !");
        }
        
        $ap = $this->getWorkbench()->getSecurity()->getAuthorizationPoint(UiPageAuthorizationPoint::class);
        try {
            $ap->authorize($node);
        } catch (AccessDeniedError $e) {
            return $this;
        }
        
        if ($position === null || ! is_numeric($position)) {
            $this->childNodes[] = $node;
        } else {
            array_splice($this->childNodes, $position, 0, [$node]);
        }
        
        return $this;
    }
    
    /**
     * Checks if the given page is equal to the page inherent to this node.
     * 
     * @param UiPageInterface $page
     * @return bool
     */
    public function isPage(UiPageInterface $page) : bool
    {
        if ($page->getUid() === $this->getUid()) {
            return true;
        }
        return false;
    }
    
    /**
     * Checks if the page inherent to this node is the parent of the given page.
     * Returns `true` if it is.
     * 
     * @param UiPageInterface $page
     * @return bool
     */
    public function isParentOf(UiPageInterface $page) : bool
    {
        foreach ($this->getChildNodes() as $childNode) {
            if ($childNode->getUid() === $page->getUid()) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Checks if the page inherent to this node is an ancestor of the given page.
     * Returns `true` if it is.
     *
     * @param UiPageInterface $page
     * @return bool
     */
    public function isAncestorOf(UiPageInterface $page) : bool
    {
        //return true;
        while (!empty($this->getChildNodes())) {
            if ($this->isParentOf($page)) {
                return true;
            }
            foreach ($this->getChildNodes() as $childNode) {
                if ($childNode->isAncestorOf($page)) {
                    return true;
                }
            }
            return false;
        }
        return false;          
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getAliasWithNamespace()
     */
    public function getAliasWithNamespace()
    {
        return $this->getPageAlias();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiMenuItemInterface::getParentPageSelector()
     */
    public function getParentPageSelector(): ?UiPageSelectorInterface
    {
        return $this->getParentNode()->getPageSelector();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getNamespace()
     */
    public function getNamespace()
    {
        return StringDataType::substringBefore($this->getPageAlias(), AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, '', false, true);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getAlias()
     */
    public function getAlias()
    {
        return StringDataType::substringAfter($this->getPageAlias(), AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, $this->getPageAlias(), false, true);
    }
    
}