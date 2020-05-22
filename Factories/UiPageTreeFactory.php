<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Model\UiPageTree;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Model\UiPageTreeNode;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Model\UiPageTreeNodeInterface;
use exface\Core\Interfaces\Selectors\UiPageGroupSelectorInterface;

class UiPageTreeFactory extends AbstractStaticFactory
{
    /**
     * Creates a complete tree with the default root page as root.
     * The `depth` property controls how many levels the tree shows.
     * 
     * To get the tree root nodes call the function `getRootNodes()` of the tree object.
     * 
     * Example:
     * 
     * App1
     *   App1_ChildPage1
     *   App1_ChildPage2
     *      App1_ChildPage2_Child1
     *      App1_ChildPage2_Child2
     *   App1_ChildPage3
     *      App1_ChildPage3_Child1
     *          App1_ChildPage3_Child1_Child
     * App2
     *   App2_ChildPage1
     *   App2_ChildPage2
     *      App2_ChildPage2_Child1
     * App3
     *   App3_ChildPage1 
     *              
     * @param Workbench $exface
     * @param int $depth
     * @return UiPageTree
     */
    public static function createFromRoot(Workbench $exface, int $depth = null) : UiPageTree
    {
        $tree = new UiPageTree($exface);
        $tree->setExpandDepth($depth);
        return $tree;
    }
    
    /**
     * Creates a complete tree with the root page, given as `rootPage` property, as root.
     * The `depth` property controls how many levels the tree shows.
     * To get the tree root nodes call the function `getRootNodes()` of the tree object.
     * 
     * Example with `App1` as root page:
     * 
     * 
     * App1_ChildPage1
     * App1_ChildPage2
     *   App1_ChildPage2_Child1
     *   App1_ChildPage2_Child2
     * App1_ChildPage3
     *   App1_ChildPage3_Child1
     *     App1_ChildPage3_Child1_Child
     *
     * 
     * @param Workbench $exface
     * @param UiPageInterface $rootPage
     * @param int $depth
     * @return UiPageTree
     */
    public static function createFromRootPage(Workbench $exface, UiPageInterface $rootPage, int $depth = null) : UiPageTree
    {
        $tree = new UiPageTree($exface);
        $tree->setRootPages([$rootPage]);
        $tree->setExpandDepth($depth);
        return $tree;
    }
    
    /**
     * Creates a tree with the as `rootPage` property given root page as root, 
     * if no root page is given the default root page is used as root.
     * The tree shows all child pages of the leaf page, given as `leafPage` property,
     * as well as all ancestor pages, and all pages on the same level as the ancestor pages,
     * till the root page.
     *  
     * The root page can also be set by calling the `setRootPages` function of the tree object.
     * 
     * To get the tree root nodes call the function `getRootNodes()` of the tree object.
     * 
     * Example with `App1_ChildPage3_Child1` as leaf page:
     * 
     * App1
     *   App1_ChildPage1
     *   App1_ChildPage2
     *   App1_ChildPage3
     *      App1_ChildPage3_Child1
     *          App1_ChildPage3_Child1_Child
     * App2
     * App3
     * 
     * @param Workbench $exface
     * @param UiPageInterface $leafPage
     * @param UiPageInterface $rootPage
     * @return UiPageTree
     */
    public static function createForLeafNode(Workbench $exface, UiPageInterface $leafPage, UiPageInterface $rootPage = null) : UiPageTree
    {
        $tree = new UiPageTree($exface);        
        $tree->setExpandPathToPage($leafPage);
        if ($rootPage !== null) {
            $tree->setRootPages([$rootPage]);
        }
        return $tree;
    }
    
    /**
     * Creates a page tree node from it's properties
     * 
     * @param WorkbenchInterface $workbench
     * @param string $alias
     * @param string $name
     * @param string $pageUid
     * @param bool $published
     * @param UiPageTreeNodeInterface $parentNode
     * @param string $description
     * @param string $intro
     * @param UiPageGroupSelectorInterface|string $pageGroupSelectors
     * @return UiPageTreeNode
     */
    public static function createNode(
        WorkbenchInterface $workbench, 
        string $alias, 
        string $name, 
        string $pageUid,
        bool $published,
        UiPageTreeNodeInterface $parentNode = null,
        string $description = null,
        string $intro = null,
        array $pageGroupSelectors = null) : UiPageTreeNode
    {
        $node = new UiPageTreeNode($workbench, $alias, $name, $pageUid, $parentNode);
        $node->setPublished($published);
        if ($description !== null) {
            $node->setDescription($description);
        }
        if ($intro !== null) {
            $node->setIntro($intro);
        }
        if ($pageGroupSelectors !== null) {
            foreach ($pageGroupSelectors as $selectorOrString) {
                $node->addGroupSelector($selectorOrString);
            }
        }
        return $node;
    }
    
    /**
     * 
     * @param UiPageInterface $page
     * @return UiPageTreeNode
     */
    public static function createNodeFromPage(UiPageInterface $page) : UiPageTreeNode
    {
        $node = new UiPageTreeNode($page->getWorkbench(), $page->getAliasWithNamespace(), $page->getName(), $page->getUid());
        $node->setDescription($page->getDescription());
        $node->setIntro($page->getIntro());
        foreach ($page->getGroupSelectors() as $groupSel) {
            $node->addGroupSelector($groupSel);
        }
        return $node;
    }
}