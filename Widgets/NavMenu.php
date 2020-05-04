<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\UiPageTreeFactory;
use exface\Core\Factories\SelectorFactory;
use exface\Core\CommonLogic\Model\UiPageTreeNode;
use exface\Core\Factories\UiPageFactory;

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
    
    private $showRootNode = false;
    
    /**
     * Returns an array of UiPageTreeNodes. The array contains the root nodes of the menu.
     * If the root page was set, the array contains the node belonging to that root page.
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
        if (count($tree->getRootNodes()) !== 1 || $this->showRootNode === true) {
            return $tree->getRootNodes();
        } else {
            return $tree->getRootNodes()[0]->getChildNodes();
        }
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
        $this->rootPage = UiPageFactory::createFromModel($this->getWorkbench(), $pageSelectorString);
        return $this;
    }
    
    /**
     * This property only applies when the NavMenu has only one root node. Set it to true, to have this root node shown in the menu.
     * If that property is not set and there is only one root node, that node won't be shown.
     * 
     * @uxon-property show_root_node
     * @uxon-type boolean
     * 
     * @param bool $trueOrFalse
     * @return NavMenu
     */
    public function setShowRootNode (bool $trueOrFalse) : NavMenu
    {
        $this->showRootNode = $trueOrFalse;
        return $this;
    }
    
    public function getRootPageAlias() : ?string
    {
        return $this->rootPage;
    }
}