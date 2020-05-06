<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\UiPageTreeFactory;
use exface\Core\CommonLogic\Model\UiPageTreeNode;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Exceptions\Security\AccessDeniedError;
use exface\Core\CommonLogic\Selectors\UiPageSelector;

/**
 * A hierarchical navigation menu starting the servers index page or a given root page.
 * 
 * Depending on the facade used, the menu will look like a tree or accordion with multiple levels.
 * 
 * Parent-child relationships are derived from the metamodel of the pages. By default the
 * menu's top-level node is the one defined in the `System.config.json` under 
 * `SERVER.INDEX_PAGE_SELECTOR`. Use `root_page_selectors` to specify the top-level
 * nodes explicitly. 
 * 
 * ## Examples
 * 
 * ### A menu from the `index` page down to the current page.
 *  
 * ```
 * {
 *  "widget_type": "NavMenu",
 *  "object_alias": "exface.Core.PAGE"
 * }
 * 
 * ```
 * 
 * ### Showing all root pages
 * 
 * ```
 * {
 *  "widget_type": "NavMenu",
 *  "object_alias": "exface.Core.PAGE",
 *  "root_page_selectors": []
 * }
 * 
 * ```
 * 
 * ### A secondary menu
 * 
 * ```
 * {
 *  "widget_type": "NavMenu",
 *  "object_alias": "exface.Core.PAGE",
 *  "root_page_selectors": [
 *      "index",
 *      "secondary-menu-root"
 *  ]
 * }
 * 
 * ```
 * 
 * @method NavMenu getWidget() 
 *
 * @author Ralf Mulansky
 *        
 */
class NavMenu extends AbstractWidget
{    
    private $showRootNode = false;
    
    private $rootPageSelectors = null;
    
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
        $tree = UiPageTreeFactory::createForLeafNode($this->getWorkbench(), $this->getPage());
        $rootSelectors = $this->getRootPageSelectors();
        if (empty($rootSelectors) === false) {
            $rootPages = [];
            foreach ($rootSelectors as $rootSelector) {
                try {
                    $rootPages[] = UiPageFactory::createFromModel($this->getWorkbench(), $rootSelector);
                } catch (AccessDeniedError $e) {
                    // Ignore not accessible roots
                }
            }
            $tree->setRootPages($rootPages);
        }
        if ($this->getShowRootNode() === false && count($tree->getRootNodes()) === 1) {
            return $tree->getRootNodes()[0]->getChildNodes();
        } else {
            return $tree->getRootNodes();
        }
    }
    
    /**
     * 
     * @return string[]|UiPageSelectorInterface[]
     */
    public function getRootPageSelectors() : array
    {
        return $this->rootPageSelectors ?? [(new UiPageSelector($this->getWorkbench(), $this->getWorkbench()->getConfig()->getOption('SERVER.INDEX_PAGE_SELECTOR')))];
    }
    
    /**
     * Array of page UIDs or aliases to use as top-level menu nodes.
     * 
     * If not set, the page specified in the `System.config.json` under 
     * `SERVER.INDEX_PAGE_SELECTOR` will be used by default.
     * 
     * @uxon-property root_page_selectors
     * @uxon-type metamodel:page[]
     * @uxon-template [""]
     * 
     * @param string[]|UiPageSelectorInterface[] $selectorsOrStrings
     * @return NavMenu
     */
    public function setRootPageSelectors(array $selectorsOrStrings) : NavMenu
    {
        $this->rootPageSelectors = $selectorsOrStrings;
        return $this;
    }
    
    /**
     * @deprecated use setRootPageSelectors() instead
     *
     * @param string $pageSelector
     * @return NavMenu
     */
    public function setRootPageAlias(string $pageSelectorString) : NavMenu
    {
        $this->rootPageSelectors = [$pageSelectorString];
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
    public function setShowRootNode(bool $trueOrFalse) : NavMenu
    {
        $this->showRootNode = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function getShowRootNode() : bool
    {
        return $this->showRootNode;
    }
}