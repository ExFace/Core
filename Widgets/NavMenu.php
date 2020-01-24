<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\UiPageTreeFactory;
use exface\Core\Factories\SelectorFactory;
use exface\Core\CommonLogic\Model\UiPageTreeNode;

/**
 * NavMenu shows a hierarchical navigational menu starting from a given root page or,
 * if no root page is given, from the default rootpage.
 * 
 * NavMenu produce a tree menu consisting of nodes. Each node contains its parent node and (if exists) its child nodes.
 * Each menu entry navigates to its inherent page when clicked.
 *  
 * ```
 * {
 *  "widget_type": "NavMenu",
 *  "object_alias": "exface.Core.PAGE"
 * }
 * 
 * ```
 * 
 * Using the optional `root_page_alias` property you can control which page should be the root page for the menu.
 * 
 * The visual representation, as always, depends on the facade.
 * 
 * @method NavMenu getWidget() 
 *
 * @author Ralf Mulansky
 *        
 */
class NavMenu extends AbstractWidget
{    
    private $rootPage = null;
    
    /**
     * Returns an array of UiPageTreeNodes. The array contains the nodes located directly under the root page in the menu.
     * If the node is an ancestor of the leaf page it contains all its child nodes as an array.
     * This structure continues till the current page node is reached.
     * If the current page has child nodes they are also shown in the menu. 
     * 
     * @return UiPageTreeNode[]
     */
    public function getMenu() : array
    {
        $leafPage = $this->getPage();
        $tree = UiPageTreeFactory::createForLeafNode($this->getWorkbench(), $leafPage);
        if ($this->rootPage !== null) {
            $tree->setRootPages([$this->rootPage]);
        }
        return $tree->getRootNodes();
    }
    
    /**
     * Specifies the alias of the root page of the menu.
     * 
     * @uxon-property root_page_alias
     * @uxon-type metamodel:page
     *
     * @param string $pageSelector
     * @return NavMenu
     */
    public function setRootPageAlias(string $pageSelectorString) : NavMenu
    {
        $this->rootPage = $this->getWorkbench()->getCMS()->getPage(SelectorFactory::createPageSelector($this->getWorkbench(), $pageSelectorString));
        return $this;
    }
}