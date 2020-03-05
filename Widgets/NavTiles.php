<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\Selectors\UiPageSelector;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\CommonLogic\Model\UiPageTreeNode;
use exface\Core\Factories\UiPageTreeFactory;
use exface\Core\Factories\UiPageFactory;

/**
 * NavTiles show a hierarchical navigational tile menu starting from a given parent page.
 * 
 * NavTiles produce a flat menu consisting of grouped tiles (= multiple Tiles widgets).
 * Each tile navigates to a child page of the menu root when clicked.
 * 
 * NavTiles are a great choice for folder pages, that do not have any meaningful widgets.
 * Just place the following short UXON code in the folder page:
 * 
 * ```
 * {
 *  "widget_type": "NavTiles",
 *  "object_alias": "exface.Core.PAGE"
 * }
 * 
 * ```
 * 
 * Using the optional `depth` property, you can control, how many levels of the menu will 
 * be listed. By default, two levels are listed, which is a good choice for menus with
 * not too many pages on each level.
 * 
 * The visual representation, as allways, depends on the facade. A typical visualization
 * is something like the Windows 8 start menu: tiles are organized in groups.
 * 
 * @method Tiles[] getWidgets()
 * @method Tiles getWidgetFirst()
 * @method Tiles getWidget() 
 *
 * @author Andrej Kabachnik
 *        
 */
class NavTiles extends WidgetGrid
{
    private $rootPageSelector = null;
    
    private $tilesBuilt = false;
    
    private $depth = 2;
    
    private $parentTileIds = [];
    
    /**
     * Specifies the alias of the root page of the menu (i.e. tiles for it's children will be generated).
     * 
     * If not set explicitly, the current page will be used as root.
     * 
     * @uxon-property root_page_alias
     * @uxon-type metamodel:page
     * 
     * @param string $pageSelector
     * @return NavTiles
     */
    public function setRootPageAlias(string $pageSelectorString) : NavTiles
    {
        $this->setRootPageSelector(new UiPageSelector($this->getWorkbench(), $pageSelectorString));
        return $this;
    }
    
    /**
     * 
     * @param UiPageSelectorInterface $selector
     * @return NavTiles
     */
    public function setRootPageSelector(UiPageSelectorInterface $selector) : NavTiles
    {
        $this->rootPageSelector = $selector;
        return $this;
    }
    
    /**
     * 
     * @return UiPageSelectorInterface|NULL
     */
    public function getRootPageSelector() : ?UiPageSelectorInterface
    {
        if ($this->rootPageSelector === null) {
            return $this->getPage()->getSelector();
        }
        return $this->rootPageSelector;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::getWidgets()
     */
    public function getWidgets(callable $filter = null)
    {
        if ($this->tilesBuilt === false) {
            $tree = UiPageTreeFactory::createFromRootPage($this->getWorkbench(), UiPageFactory::createFromModel($this->getWorkbench(), $this->getRootPageSelector()), $this->getDepth());
            $nodes = $tree->getRootNodes();
            $this->createTileGroupFromNodes($nodes, UiPageFactory::createFromModel($this->getWorkbench(), $this->getRootPageSelector())->getName());
            
            $this->tilesBuilt = true;
            
        }
        return parent::getWidgets();
    }    
        
    /**
     *
     * @param UiPageTreeNode[] $nodes
     * @param string $caption
     * @param int $depth
     * @param Tile $upperLevelTile
     * @return Tiles
     */
    protected function createTileGroupFromNodes(array $nodes, string $caption, Tile $upperLevelTile = null) : Tiles
    {
        $tiles = WidgetFactory::create($this->getPage(), 'Tiles', $this);
        $tiles->setCaption($caption);
        $this->addWidget($tiles);
        
        foreach ($nodes as $node) {
            $tile = $this->createTileFromTreeNode($node, $tiles);
            $tiles->addWidget($tile);
            if ($upperLevelTile !== null) {
                $this->parentTileIds[$tile->getId()] = $upperLevelTile;
            }
            if ($node->hasChildNodes()) {
                $this->createTileGroupFromNodes($node->getChildNodes(), $caption . ' > ' . $node->getName(), $tile);
            }
        }
        
        return $tiles;
    }
    
    /**
     * 
     * @param UiPageTreeNode $node
     * @param iContainOtherWidgets $container
     * @return Tile
     */
    protected function createTileFromTreeNode(UiPageTreeNode $node, iContainOtherWidgets $container) : Tile
    {
        /* @var $tile \exface\Core\Widgets\Tile */
        $tile = WidgetFactory::create($container->getPage(), 'Tile', $container);
        $tile->setTitle($node->getName());
        $tile->setSubtitle($node->getDescription());
        $tile->setWidth('0.5');
        $hint = $node->hasIntro() ? $node->getIntro() : $node->getDescription();
        $tile->setHint($node->getName() . ($hint ? ":\n" . $hint : ''));
        $tile->setAction(new UxonObject([
            'alias' => 'exface.Core.GoToPage',
            'page_alias' => $node->getPageSelector()
            
        ]));
        return $tile;
    }
    
    /**
     *
     * @return int
     */
    public function getDepth() : int
    {
        return $this->depth;
    }
    
    /**
     * Controls the depth of the menu (2 by default).
     * 
     * If set to 1, the tile menu will include tiles for the direct children of the root page
     * and their children (i.e. 2 menu levels).
     * 
     * @uxon-property depth
     * @uxon-type integer
     * @uxon-default 2
     * 
     * @param int $value
     * @return NavTiles
     */
    public function setDepth(int $value) : NavTiles
    {
        $this->depth = $value;
        return $this;
    }
    
    /**
     * Returns all tiles in a flat array
     * 
     * @param callable $filter
     * @return Tile[]
     */
    public function getTiles(callable $filter = null) : array
    {
        $tiles = [];
        foreach ($this->getWidgets($filter) as $w) {
            if ($w instanceof WidgetGroup) {
                $tiles = array_merge($tiles, $w->getWidgets($filter));
            } else {
                $tiles[] = $w;
            }
        }
        return $tiles;
    }
    
    /**
     * Returns the tile, representing the parent page of the page of the given tile.
     * 
     * This method can be used to let tiles inherit properties (e.g. colors or icons)
     * from the respective parent menu levels.
     * 
     * @param Tile $tile
     * @return Tile|NULL
     */
    public function getUpperLevelTile(Tile $tile) : ?Tile
    {
        return $this->parentTileIds[$tile->getId()];
    }
}