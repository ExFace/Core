<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Model\UiPageInterface;

/**
 * NavMenu shows a navigation menu similar to NavTiles but as a list of links
 *
 * @author Andrej Kabachnik
 *        
 */
class NavMenu extends WidgetGrid
{    
    private $menu = null;
    
    /**
     * 
     * @param UiPageSelectorInterface $parentPageSelector
     * @throws WidgetConfigurationError
     * @return DataSheetInterface
     */
    protected function getMenuDataSheet(UiPageSelectorInterface $parentPageSelector) : DataSheetInterface
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.PAGE');
        $ds->getColumns()->addMultiple(['CMS_ID', 'NAME', 'DESCRIPTION', 'INTRO', 'ALIAS']);
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
    
    public function getMenuArray(UiPageInterface $currentPage = null, string $childPageId = null) : array
    {
        $menuPart = [];
        if ($currentPage === null) {
            $currentPage = $this->getPage();
        }
        $pageSelector = $currentPage->getSelector();
        
        //get all data for child pages from currentPage
        $dataSheet = $this->getMenuDataSheet($pageSelector);
        foreach ($dataSheet->getRows() as $row) {
            $menuItem = [
                'CMS_ID' => $row['CMS_ID'],
                'NAME' => $row['NAME'],
                'ALIAS' => $row['ALIAS'],
            ];
            if ($childPageId && $menuItem['CMS_ID'] == $childPageId && $this->menu !== null) {
                
                $menuItem['SUB_MENU'] = $this->menu;
            }
            $menuPart[] = $menuItem; 
        }
        if (!empty($menuPart)) {
            $this->menu = $menuPart;
        }
        //get CMS_ID from current page
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.PAGE');
        $ds->getColumns()->addMultiple(['CMS_ID']);        
        if ($pageSelector->isAlias()) {
            $alias = 'ALIAS';
        } elseif ($pageSelector->isUid()) {
            $alias = 'UID';
        } elseif ($pageSelector->isCmsId()) {
            $alias = 'CMS_ID';
        } else {
            throw new WidgetConfigurationError($this, 'Invalid page selector "' . $pageSelector->toString() . '" in widget ' . $this->getWidgetType() . '"!');
        }        
        $ds->addFilterFromString($alias, $pageSelector->toString(), '==');        
        $ds->dataRead();
        $row = $ds->getRow(0);
        $currentPageId = $row['CMS_ID'];
        
        //if parent page is not the "Home" page continue building menu by going one level up
        if ($currentPageId !== '1') {
            $parentPage = $currentPage->getMenuParentPage();
            $this->getMenuArray($parentPage, $currentPageId);
        }
        return $this->menu;
        
        
    }

}