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
use exface\Core\Factories\ConditionFactory;
use exface\Core\Factories\ConditionGroupFactory;

/**
 * NavTiles show a navigational tile menu starting from a given parent page.
 * 
 * This a simple shortcut to create tile menus - much quicker, than building
 * them manually via separate tiles.
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
     * 
     * @param string $pageSelector
     * @return NavTiles
     */
    public function setRootPageAlias(string $pageSelectorString) : NavTiles
    {
        $this->setRootPageSelector(new UiPageSelector($this->getWorkbench(), $pageSelectorString));
        return $this;
    }
    
    public function setRootPageSelector(UiPageSelectorInterface $selector) : NavTiles
    {
        $this->rootPageSelector = $selector;
        return $this;
    }
    
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
            $pageSheet = $this->getMenuDataSheet($this->getRootPageSelector());
            $this->createTileGroup($pageSheet, $this->getWorkbench()->getCMS()->getPage($this->getRootPageSelector())->getName(), $this->getDepth());
            
            $this->tilesBuilt = true;
            
        }
        return parent::getWidgets();
    }
    
    protected function createTileGroup(DataSheetInterface $pageSheet, string $caption, int $depth, Tile $upperLevelTile = null) : Tiles
    {
        $tiles = WidgetFactory::create($this->getPage(), 'Tiles', $this);
        $tiles->setCaption($caption);
        $this->addWidget($tiles);
        
        foreach ($pageSheet->getRows() as $row) {
            $tile = $this->createTileFromPageDataRow($row, $tiles);
            $tiles->addWidget($tile);
            if ($upperLevelTile !== null) {
                $this->parentTileIds[$tile->getId()] = $upperLevelTile->getId();
            }
            if ($depth > 1) {
                $parentPageSelector = new UiPageSelector($this->getWorkbench(), $row['CMS_ID']);
                $childrenSheet = $this->getMenuDataSheet($parentPageSelector);
                if ($childrenSheet->isEmpty() === false) {
                    $this->createTileGroup($childrenSheet, $row['NAME'], ($depth-1), $tile);
                }
            }
        }
        
        return $tiles;
    }
    
    protected function createTilesForParent(UiPageSelectorInterface $parentPageSelector, iContainOtherWidgets $container) : Tiles
    {
        $pageSheet = $this->getMenuDataSheet($parentPageSelector);
        
        foreach ($pageSheet->getRows() as $row) {
            $tile = $this->createTileFromPageDataRow($row, $container);
            $container->addWidget($tile);
        }
        return $container;
    }
    
    protected function createTileFromPageDataRow(array $row, iContainOtherWidgets $container) : Tile
    {
        /* @var $tile \exface\Core\Widgets\Tile */
        $tile = WidgetFactory::create($container->getPage(), 'Tile', $container);
        $tile->setTitle($row['NAME']);
        $tile->setSubtitle($row['DESCRIPTION']);
        $tile->setWidth('0.5');
        $tile->setHint($row['NAME'] . ":\n" . $row['DESCRIPTION']);
        $tile->setAction(new UxonObject([
            'alias' => 'exface.Core.GoToPage',
            'page_alias' => $row['ALIAS']
            
        ]));
        return $tile;
    }
    
    /**
     * 
     * @param UiPageSelectorInterface $parentPageSelector
     * @param int $depth
     * @throws WidgetConfigurationError
     * @return DataSheetInterface
     */
    protected function getMenuDataSheet(UiPageSelectorInterface $parentPageSelector) : DataSheetInterface
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.PAGE');
        $cols = $ds->getColumns();
        $cols->addFromExpression('NAME');
        $cols->addFromExpression('DESCRIPTION');
        $cols->addFromExpression('ALIAS');
        
        $ds->getSorters()->addFromString('MENU_POSITION');
        
        if ($parentPageSelector->isAlias()) {
            $parentAlias = 'MENU_PARENT__ALIAS';
        } elseif ($parentPageSelector->isUid()) {
            $parentAlias = 'MENU_PARENT__UID';
        } elseif ($parentPageSelector->isCmsId()) {
            $parentAlias = 'MENU_PARENT';
        } else {
            throw new WidgetConfigurationError($this, 'Invalid page selector "' . $parentPageSelector->toString() . '" in widget ' . $this->getWidgetType() . '"!');
        }
        
        $ds->addFilterFromString($parentAlias, $parentPageSelector->toString(), '==');
        $ds->addFilterFromString('MENU_VISIBLE', 1, '==');
        
        $ds->dataRead();
        return $ds;
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
    
    public function getUpperLevelTile(Tile $tile) : ?Tile
    {
        return $this->parentTileIds[$tile->getId()];
    }
}