<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Model\UiPageTree;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\CommonLogic\Workbench;

class UiPageTreeFactory extends AbstractStaticFactory
{
    public static function createFromRoot(Workbench $exface, int $depth = null) : UiPageTree
    {
        $tree = new UiPageTree($exface);
        $tree->setExpandDepth($depth);
        return $tree;
    }
    
    public static function createFromRootPage(Workbench $exface, UiPageInterface $rootPage, int $depth = null) : UiPageTree
    {
        $tree = new UiPageTree($exface);
        $tree->setRootPages([$rootPage]);
        $tree->setExpandDepth($depth);
        return $tree;
    }
    
    public static function createForLeafNode(Workbench $exface, UiPageInterface $leafPage, UiPageInterface $rootPage = null, int $depthForOtherNodes = 1) : UiPageTree
    {
        $tree = new UiPageTree($exface);
        $tree->setExpandPathToPage($leafPage);
        if ($rootPage !== null) {
            $tree->setRootPages([$rootPage]);
        }
        $tree->setExpandDepth($depthForOtherNodes);
        return $tree;
    }
    
    public static function createBreadcrumbsToPage(Workbench $exface, UiPageInterface $page) : UiPageTree
    {
        $tree = new UiPageTree($exface);
        $tree->setExpandPathToPage($page);
        $tree->setExpandPathOnly(true);
        return $tree;
    }
}