<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\Model\UiPageTreeNode;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Exceptions\InvalidArgumentException;

interface UiPageTreeNodeInterface
{
    public function __construct(WorkbenchInterface $exface, string $pageAlias, string $name, string $uid, UiPageTreeNode $parentNode = null);
    
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
     * @param UiPageTreeNode $parentNode
     * @return UiPageTreeNode
     */
    public function setParentNode(UiPageTreeNode $parentNode) : UiPageTreeNode;
    
    /**
     * 
     * @return bool
     */
    public function hasParentNode() : bool;
    
    /**
     * 
     * @return UiPageTreeNode|NULL
     */
    public function getParentNode() : ?UiPageTreeNode;
    
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
     * @return UiPageTreeNode
     */
    public function setIntro (string $intro) : UiPageTreeNode;
    
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
     * @return UiPageTreeNode
     */
    public function setDescription (string $descpription) : UiPageTreeNode;
    
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
     * @param UiPageTreeNode $node
     * @param int $position
     * @throws InvalidArgumentException
     * @return UiPageTreeNode
     */
    public function addChildNode(UiPageTreeNode $node, int $position = null) : UiPageTreeNode;
    
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