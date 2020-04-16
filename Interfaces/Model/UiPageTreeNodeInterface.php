<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Exceptions\InvalidArgumentException;

interface UiPageTreeNodeInterface extends UiMenuItemInterface
{
    
    /**
     * 
     * @return string
     */
    public function getPageAlias() : string;
    
    /**
     * 
     * @param UiPageTreeNodeInterface $parentNode
     * @return UiPageTreeNodeInterface
     */
    public function setParentNode(UiPageTreeNodeInterface $parentNode) : UiPageTreeNodeInterface;
    
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