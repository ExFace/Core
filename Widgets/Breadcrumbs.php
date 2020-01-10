<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\UiPageTreeFactory;
use exface\Core\Factories\SelectorFactory;

/**
 * Breadcrumbs show links to all ancestor pages of the current page starting from a given root page or,
 * if no root page is given, from the default root page.
 * 
 * Breadcrumbs produce a tree menu consisting of nodes. Each node contains its parent node and (if exists) its child node
 * that is either an ancestor of the current page, the parent of the current page or the current page itself.
 * 
 * Each menu entry navigates to its inherent page when clicked.
 *  
 * ```
 * {
 *  "widget_type": "Breadcrumbs",
 *  "object_alias": "exface.Core.PAGE"
 * }
 * 
 * ```
 * 
 * Using the optional `root_page_alias` property you can control which page should be the root page for the menu.
 * 
 * The visual representation, as always, depends on the facade.
 * 
 * @method Breadcrumbs getWidget() 
 *
 * @author Ralf Mulansky
 *        
 */
class Breadcrumbs extends AbstractWidget
{    
    private $rootPage = null;
    
    public function getBreadcrumbs() : array
    {
        $leafPage = $this->getPage();
        $tree = UiPageTreeFactory::createBreadcrumbsToPage($this->getWorkbench(), $leafPage);
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
    public function setRootPageAlias(string $pageSelectorString) : Breadcrumbs
    {
        $this->rootPage = $this->getWorkbench()->getCMS()->getPage(SelectorFactory::createPageSelector($this->getWorkbench(), $pageSelectorString));
        return $this;
    }
}