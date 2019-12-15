<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Interfaces\Widgets\iHaveContextualHelp;
use exface\Core\Widgets\Traits\iHaveButtonsAndToolbarsTrait;
use exface\Core\Widgets\Traits\iHaveContextualHelpTrait;
use exface\Core\Interfaces\Widgets\iHaveToolbars;
use exface\Core\Interfaces\Widgets\iShowMessageList;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * A Form is a Panel with buttons.
 * 
 * Forms and their derivatives provide input data for actions. Each `Form` consits of 
 * a grid with input widgets and a button toolbar. Optionally secondary `toolbars`
 * can be added if the facade supports this.
 * 
 * It is very easy to add input widgets to a form: just specify the `attribute_alias` for
 * every widget to insert it's default editor. The input widgets are arranged in a grid
 * layout. Use `columns_in_grid` to control the number of column in the grid. Nested layout 
 * widgets like `WidgetGroup`, `WidgetGrid`, `InlineGroup`, can help organize inputs.
 *
 * Note, that While having similar purpose as HTML forms, `Form` widgets are not the same! 
 * They can be nested, they may include tabs, optional panels with lazy loading, etc. Thus, 
 * in most HTML-facades the `Form` widget will not be mapped to an HTML form, but rather 
 * to some container element (e.g. `<div>`), while fetching data from the form will need 
 * to be custom implemented (i.e. with JavaScript).
 *
 * @author Andrej Kabachnik
 *        
 */
class Form extends Panel implements iHaveButtons, iHaveToolbars, iShowMessageList, iHaveContextualHelp
{
    use iHaveButtonsAndToolbarsTrait;
    use iHaveContextualHelpTrait {
        getHideHelpButton as getHideHelpButtonViaTrait;
    }
    
    private $messageList = null;

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Widgets\Container::getChildren()
     */
    public function getChildren() : \Iterator
    {
        foreach (parent::getChildren() as $child) {
            yield $child;
        }
        
        foreach ($this->getToolbars() as $tb) {
            yield $tb;
        }
        
        // Add the help button, so pages will be able to find it when dealing with the ShowHelpDialog action.
        // IMPORTANT: Add the help button to the children only if it is not hidden. This is needed to hide the button in
        // help widgets themselves, because otherwise they would produce their own help widgets, with - in turn - even
        // more help widgets, resulting in an infinite loop.
        if (! $this->getHideHelpButton()) {
            yield $this->getHelpButton();
        }
    }
    
    public function getToolbarWidgetType()
    {
        return 'FormToolbar';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowMessageList::getMessageList()
     */
    public function getMessageList() : MessageList
    {
        if ($this->messageList === null) {
            $this->messageList = WidgetFactory::create($this->getPage(), 'MessageList', $this);
        }
        return $this->messageList;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowMessageList::hasMessages()
     */
    public function hasMessages() : bool
    {
        if ($this->messageList !== null && $this->getMessageList()->isEmpty() === false) {
            return true;
        } 
        
        return false;
    }
    
    /**
     * Array of widgets to be placed in the form (inputs or any other kind of widget).
     * 
     * @uxon-property widgets
     * @uxon-type \exface\Core\Widgets\Input[]|\exface\Core\Widgets\AbstractWidget[]
     * @uxon-template [{"attribute_alias": ""}]
     * 
     * @see \exface\Core\Widgets\Container::setWidgets()
     */
    public function setWidgets($widget_or_uxon_array)
    {
        return parent::setWidgets($widget_or_uxon_array);
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
                    $row = array_merge($row, $this->getHelpDataRowFromAttribute($attr, $widget));
                }
                $dataSheet->addRow($row);
            }
        }
        return $dataSheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::getHideHelpButton()
     */
    public function getHideHelpButton($default = false) : ?bool
    {
        return $this->getHideHelpButtonViaTrait(null) ?? $this->hasParent() === true;
    }
}