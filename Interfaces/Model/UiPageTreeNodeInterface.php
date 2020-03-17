<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\Model\UiPageTreeNode;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Exceptions\InvalidArgumentException;

interface UiPageTreeNodeInterface
{
     /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench();
    
    /**
     * 
     * @return string
     */
    public function getName() : string;
    
    /**
     * 
     * @return string
     */
    public function getPageAlias() : string;
    
    /**
     * 
     * @return string
     */
    public function getUid() : string;
    
    /**
     * 
     * @param UiPageTreeNodeInterface $parentNode
     * @return UiPageTreeNodeInterface
     */
    public function setParentNode(UiPageTreeNodeInterface $parentNode) : UiPageTreeNodeInterface;
    
    /**
     * 
     * @return bool
     */
    public function hasParentNode() : bool;
    
    /**
     * 
     * @return UiPageTreeNodeInterface|NULL
     */
    public function getParentNode() : ?UiPageTreeNodeInterface;
    
    /**
     * Returns the nodes inherent page.
     * 
     * @return UiPageInterface
     */
    public function getPage() : UiPageInterface;
    
    /**
     * 
     * @return UiPageSelectorInterface
     */
    public function getPageSelector() : UiPageSelectorInterface;
    
    /**
     * 
     * @param string $intro
     * @return UiPageTreeNodeInterface
     */
    public function setIntro (string $intro) : UiPageTreeNodeInterface;
    
    /**
     * 
     * @return bool
     */
    public function hasIntro() : bool;
    
    /**
     * 
     * @return string
     */
    public function getIntro() : string;
    
    /**
     * 
     * @param string $descpription
     * @return UiPageTreeNodeInterface
     */
    public function setDescription (string $descpription) : UiPageTreeNodeInterface;
    
    /**
     * 
     * @return string
     */
    public function getDescription() : string;
    
    /**
     * 
     * @return bool
     */
    public function hasChildNodes() : bool;
    
    /**
     * 
     * @return array
     */
    public function getChildNodes() : array;
    
    /**
     *
     * @return UiPageTreeNodeInterface
     */
    public function resetChildNodes() : UiPageTreeNodeInterface;
    
    /**
     * 
     * @param UiPageTreeNodeInterface $node
     * @param int $position
     * @throws InvalidArgumentException
     * @return UiPageTreeNodeInterface
     */
    public function addChildNode(UiPageTreeNodeInterface $node, int $position = null) : UiPageTreeNodeInterface;
    
    /**
     *
     * @param bool $trueOrFalse
     * @return UiPageTreeNodeInterface
     */
    public function setChildNodesLoaded(bool $trueOrFalse) : UiPageTreeNodeInterface;
    
    /**
     *
     * @return bool
     */
    public function getChildNodesLoaded() : bool;
    
    /**
     * Checks if the given page is equal to the page inherent to this node.
     * 
     * @param UiPageInterface $page
     * @return bool
     */
    public function isPage(UiPageInterface $page) : bool;
    
    /**
     * Checks if the page inherent to this node is the parent of the given page.
     * Returns `true` if it is.
     * 
     * @param UiPageInterface $page
     * @return bool
     */
    public function isParentOf(UiPageInterface $page) : bool;
    
    /**
     * Checks if the page inherent to this node is an ancestor of the given page.
     * Returns `true` if it is.
     *
     * @param UiPageInterface $page
     * @return bool
     */
    public function isAncestorOf(UiPageInterface $page) : bool;   

}