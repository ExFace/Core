<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\Selectors\UiPageSelector;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;

/**
 * A Tile is basically a big fancy button, that can display additional information (KPIs, etc.).
 *
 * @author Andrej Kabachnik
 *        
 */
class NavTiles extends WidgetGrid
{
    private $rootPageSelector = null;
    
    private $tilesBuilt = false;
    
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
     * @param callable $filter
     * @return Tile[]
     */
    public function getTiles(callable $filter = null) : array
    {
        return $this->getWidgets($filter);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::getWidgets()
     */
    public function getWidgets(callable $filter = null)
    {
        if ($this->tilesBuilt === false) {
            $this->tilesBuilt = true;
            foreach ($this->getMenuDataSheet($this->getRootPageSelector())->getRows() as $row) {
                /* @var $tile \exface\Core\Widgets\Tile */
                $tile = WidgetFactory::create($this->getPage(), 'Tile', $this);
                $tile->setTitle($row['NAME']);
                $tile->setSubtitle($row['DESCRIPTION']);
                $tile->setWidth('0.5');
                $tile->setHint($row['NAME'] . ":\n" . $row['DESCRIPTION']);
                $tile->setAction(new UxonObject([
                    'alias' => 'exface.Core.GoToPage', 
                    'page_alias' => $row['ALIAS']
                    
                ]));
                $this->addWidget($tile);
            }
        }
        return parent::getWidgets();
    }
    
    /**
     * 
     * @param UiPageSelectorInterface $rootPageSelector
     * @param int $depth
     * @throws WidgetConfigurationError
     * @return DataSheetInterface
     */
    protected function getMenuDataSheet(UiPageSelectorInterface $rootPageSelector, int $depth = 1) : DataSheetInterface
    {
        if ($depth > 1) {
            throw new WidgetConfigurationError($this, 'Nav widgets with a depth of more than 1 menu level currently not possible!');
        }
        if ($depth < 1) {
            throw new WidgetConfigurationError($this, 'Invalid menu depth value "' . $depth . '" for widget ' . $this->getWidgetType() . ': use only positive integers!');
        }
        
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.PAGE');
        $cols = $ds->getColumns();
        $cols->addFromExpression('NAME');
        $cols->addFromExpression('DESCRIPTION');
        $cols->addFromExpression('ALIAS');
        
        $ds->getSorters()->addFromString('MENU_POSITION');
        
        if ($rootPageSelector->isAlias()) {
            $ds->addFilterFromString('MENU_PARENT__ALIAS', $rootPageSelector->toString(), '==');
        } elseif ($rootPageSelector->isUid()) {
            $ds->addFilterFromString('MENU_PARENT__UID', $rootPageSelector->toString(), '==');
        } elseif ($rootPageSelector->isCmsId()) {
            $ds->addFilterFromString('MENU_PARENT', $rootPageSelector->toString(), '==');
        } else {
            throw new WidgetConfigurationError($this, 'Invalid page selector "' . $rootPageSelector->toString() . '" in widget ' . $this->getWidgetType() . '"!');
        }
        
        $ds->dataRead();
        return $ds;
    }
}
?>