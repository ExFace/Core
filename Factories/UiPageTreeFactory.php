<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Model\UiPageTree;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\CommonLogic\Workbench;

class UiPageTreeFactory extends AbstractStaticFactory
{
    /**
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
     * 
     * @param Workbench $exface
     * @param UiPageInterface $page
     * @return UiPageTree
     */
    public static function createBreadcrumbsToPage(Workbench $exface, UiPageInterface $page) : UiPageTree
    {
        $tree = new UiPageTree($exface);
        $tree->setExpandPathToPage($page);
        $tree->setExpandPathOnly(true);
        return $tree;
    }
}