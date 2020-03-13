<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Model\UiPageTree;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Model\UiPageTreeNode;

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
     * The root page can also be set by calling the `setRootPages` function of the tree object and giving the root page as an array.
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
        $node = new UiPageTreeNode($exface, $leafPage->getAlias(), $leafPage->getName(), $leafPage->getId());
        $node->setDescription($leafPage->getDescription());
        $node->setIntro($leafPage->getIntro());
        $tree->setExpandPathToNode($node);
        if ($rootPage !== null) {
            $tree->setRootPages([$rootPage]);
        }
        return $tree;
    }
    
    /**
     * /**
     * Creates a tree with the as `rootPage` property given root page as root, 
     * if no root page is given the default root page is used as root.
     * The tree shows all ancestor pages of the leaf page, given as `leafPage` property, till the root page.
     * That type of tree is also called breadcrumbs.
     *  
     * The root page can also be set by calling the `setRootPages` function of the tree object and giving the root page as an array.
     * 
     * To get the tree root nodes call the function `getRootNodes()` of the tree object.
     * 
     * Example with `App1_ChildPage3_Child1` as leaf page:
     * 
     * App1
     *   App1_ChildPage3
     *      App1_ChildPage3_Child1
     *          App1_ChildPage3_Child1_Child
     * 
     * @param Workbench $exface
     * @param UiPageInterface $page 
     * @param UiPageInterface $rootPage
     * @return UiPageTree
     */
    public static function createBreadcrumbsToPage(Workbench $exface, UiPageInterface $page, UiPageInterface $rootPage = null) : UiPageTree
    {
        $tree = new UiPageTree($exface);
        $node = new UiPageTreeNode($exface, $page->getAlias(), $page->getName(), $page->getId());
        $node->setDescription($page->getDescription());
        $node->setIntro($page->getIntro());
        $tree->setExpandPathToNode($node);
        if ($rootPage !== null) {
            $tree->setRootPages([$rootPage]);
        }
        $tree->setExpandPathOnly(true);
        return $tree;
    }
}