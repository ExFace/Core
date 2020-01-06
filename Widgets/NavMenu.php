<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\UiPageTreeFactory;
use exface\Core\CommonLogic\Model\UiPageTreeNode;

/**
 * NavMenu shows a navigation menu similar to NavTiles but as a list of links
 *
 * @author Andrej Kabachnik
 *        
 */
class NavMenu extends AbstractWidget
{    
    public function getMenu() : array
    {
        $leafPage = $this->getPage();
        $tree = UiPageTreeFactory::createForLeafNode($this->getWorkbench(), $leafPage);
        return $tree->getRootNodes();
    }

}