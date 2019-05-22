<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveContextualHelp;
use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Widgets\iAmCollapsible;
use exface\Core\CommonLogic\Traits\WidgetLayoutTrait;
use exface\Core\Widgets\Traits\iAmCollapsibleTrait;
use exface\Core\Widgets\Traits\iHaveIconTrait;
use exface\Core\Widgets\Traits\iSupportLazyLoadingTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Traits\iHaveContextualHelpTrait;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;

/**
 * A panel is a visible container with a configurable layout (number of columns,
 * etc.) and optional support for lazy-loading of content.
 *
 * The panel is the base widget for many containers, that show multiple smaller
 * widgets in a column-based (newspaper-like) layout.
 *
 * @see Form - Panel with buttons
 * @see Dashboard - Panel with a common customizer (common filters, buttons, etc.)
 * @see WidgetGroup - Small panel to easily group input widgets
 * @see SplitPanel - Special resizable panel to be used in SplitVertical and SplitHorizontal widgets
 * @see Tab - Special panel to be used in the Tabs widget
 *     
 * @author Andrej Kabachnik
 *        
 */
class Panel extends WidgetGrid implements iSupportLazyLoading, iHaveIcon, iAmCollapsible, iFillEntireContainer, iHaveContextualHelp
{
    use WidgetLayoutTrait;
    use iAmCollapsibleTrait;
    use iHaveIconTrait;
    use iSupportLazyLoadingTrait;
    use iHaveContextualHelpTrait {
        getHideHelpButton as getHideHelpButtonViaTrait;
    }
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Widgets\Traits\iSupportLazyLoadingTrait::getLazyLoadingActionUxonDefault()
     */
    protected function getLazyLoadingActionUxonDefault() : UxonObject
    {
        return new UxonObject([
            'alias' => 'exface.Core.ShowWidget'
        ]);
    }

    /**
     *
     * {@inheritdoc} If the parent widget of a panel has other children (siblings of the panel),
     *               they should be moved to the panel itself, once it is added to it's paren.
     *              
     * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::getAlternativeContainerForOrphanedSiblings()
     * @return Panel
     */
    public function getAlternativeContainerForOrphanedSiblings()
    {
        return $this;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::getHelpWidget()
     */
    public function getHelpWidget(iContainOtherWidgets $help_container) : iContainOtherWidgets
    {
        $table = $this->getHelpTable($help_container);
        $help_container->addWidget($table);
        
        
        $data_sheet = $this->getHelpData($this->getWidgets(), DataSheetFactory::createFromObject($table->getMetaObject()));
        
        if ($data_sheet->isEmpty() === true) {
            $data_sheet->addRow([
                'TITLE' => '',
                'DESCRIPTION' => $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.SHOWHELPDIALOG.NO_HELP'),
                'GROUP' => ''
            ]);
        }
        
        // Mark the data sheet is fresh here - even if it is empty! - because
        // otherwise further code might attempt to load data from the unreadable
        // help meta object.
        $data_sheet->setFresh(true);
        
        $table->prefill($data_sheet);
        
        return $help_container;
    }
    
    /**
     * Adds information about each widget in the array to the given sheet.
     *
     * @param array $widgets
     * @param DataSheetInterface $dataSheet
     * @param string $groupName
     * @return DataSheetInterface
     */
    protected function getHelpData(array $widgets, DataSheetInterface $dataSheet, string $groupName = null) : DataSheetInterface
    {
        foreach ($widgets as $widget) {
            if ($widget->isHidden()) {
                continue;
            }
            
            if ($widget instanceof iContainOtherWidgets) {
                if ($widget->getCaption()) {
                    $groupName = $widget->getCaption();
                }
                $dataSheet = $this->getHelpData($widget->getWidgets(), $dataSheet, $groupName);
            } elseif ($widget->getCaption()) {
                $row = [
                    'TITLE' => $widget->getCaption(),
                    'GROUP' => $groupName ?? ''
                ];
                if ($widget instanceof iShowSingleAttribute && $attr = $widget->getAttribute()) {
                    $row = array_merge($row, $this->getHelpRowFromAttribute($attr));
                }
                $dataSheet->addRow($row);
            }
        }
        return $dataSheet;
    }
    
    /**
     * Returns a row (assotiative array) for a data sheet with exface.Core.USER_HELP_ELEMENT filled with information about
     * the given attribute.
     * The inforation is derived from the attributes meta model.
     *
     * @param MetaAttributeInterface $attr
     * @return string[]
     */
    protected function getHelpRowFromAttribute(MetaAttributeInterface $attr)
    {
        $row = array();
        $row['DESCRIPTION'] = $attr->getShortDescription() ? rtrim(trim($attr->getShortDescription()), ".") . '.' : '';
        
        if (! $attr->getRelationPath()->isEmpty()) {
            $row['DESCRIPTION'] .= $attr->getObject()->getShortDescription() ? ' ' . rtrim($attr->getObject()->getShortDescription(), ".") . '.' : '';
        }
        return $row;
    }
    
    public function getHideHelpButton($default = false) : ?bool
    {
        return $this->getHideHelpButtonViaTrait(null) ?? $this->hasParent() === true;
    }
}
?>