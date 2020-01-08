<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\UiPageTreeFactory;
use exface\Core\Factories\SelectorFactory;

/**
 * NavMenu shows a navigation menu similar to NavTiles but as a list of links
 *
 * @author Andrej Kabachnik
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
    public function setRootPageAlias(string $pageSelectorString) : NavMenu
    {
        $this->rootPage = $this->getWorkbench()->getCMS()->getPage(SelectorFactory::createPageSelector($this->getWorkbench(), $pageSelectorString));
        return $this;
    }
}