<?php
namespace exface\Core\Actions;

use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\Contexts\ContextActionTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;

/**
 * Displays a popup-table with all instances of a meta object in the object basket.
 * 
 * Using the context_scope and context_alias UXON properties the action can be 
 * used to fetch object instances from different baskets. 
 *
 * @author Andrej Kabachnik
 *        
 */
class ObjectBasketShowDialog extends ShowDialog
{
    use ContextActionTrait; 
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowWidget::init()
     */
    protected function init()
    {
        parent::init();
        $this->setContextAlias('exface.Core.ObjectBasketContext');
        $this->setPrefillDisabled(true);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowWidget::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        if ($task->hasParameter(ContextApi::TASK_PARAMETER_CONTEXT_SCOPE)) {
            $this->setContextScope($task->getParameter(ContextApi::TASK_PARAMETER_CONTEXT_SCOPE));
        }
        return parent::perform($task, $transaction);
    }

    /**
     * The ObjectBasketDialog auto-creates a table with a remove-button and a
     * MenuButton for action marked to be used in the object basket.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowDialog::createDialogWidget()
     */
    protected function createDialogWidget(UiPageInterface $page, WidgetInterface $contained_widget = null)
    {
        /* @var $dialog \exface\Core\Widgets\Dialog */
        $dialog = WidgetFactory::create($page, 'Dialog', $this->getWidgetDefinedIn());
        $dialog->setCaption($this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.OBJECTBASKET'));
        $dialog->setLazyLoading(false);
        
        /* @var $table \exface\Core\Widgets\DataTable */
        $table = WidgetFactory::create($dialog->getPage(), 'DataTable', $dialog);
        $table->setLazyLoading(false);
        $table->setPaginate(false);
        $table->setHideFooter(true);
        $table->setMultiSelect(true);
        $table->setMultiSelectAllSelected(true);
        $table->getConfiguratorWidget()->addFilter(
            $table->getConfiguratorWidget()->createFilterWidget($table->getMetaObject()->getUidAttributeAlias(), UxonObject::fromArray(['widget_type' => 'InputHidden']))
        );
        
        // If the table has no columns, try to generate some: either use the UID column
        // if there is a UID attribute and that is visible or just take the first 10
        // visible attributes.
        if (empty($table->getColumns())) {
            if ($table->getMetaObject()->hasUidAttribute() && ! $table->getMetaObject()->getUidAttribute()->isHidden()) {
                $table->addColumn($table->createColumnFromAttribute($table->getMetaObject()->getUidAttribute()));
            } else {
                $cnt = 1;
                foreach ($table->getMetaObject()->getAttributeGroup('~VISIBLE') as $attr) {
                    $table->addColumn($table->createColumnFromAttribute($attr));
                    if ($cnt >= 10) {
                        break;
                    } else {
                        $cnt++;
                    }
                }
            }
        }
        $dialog->addWidget($table);
        
        // Prefill table
        $ds = $this->getContext()->getBasketByObject($this->getMetaObject())->copy();
        if (! $ds->isEmpty()) {
            $ds->getFilters()->addConditionFromColumnValues($ds->getUidColumn());
            $table->prepareDataSheetToPrefill($ds);
            $table->prefill($ds);
        }
        
        // Add remove button
        $button = $dialog->createButton();
        $button->setActionAlias('exface.Core.ObjectBasketRemove');
        $button->setInputWidget($table);
        $button->getAction()->setContextScope($this->getContextScope())->setContextAlias($this->getContextAlias());
        $dialog->addButton($button);
        
        // Add info button
        $info_button = $dialog->createButton();
        $info_button->setActionAlias('exface.Core.ShowObjectInfoDialog');
        $info_button->setInputWidget($table);
        $dialog->addButton($info_button);
        
        // Add actions menu
        /* @var $menu \exface\Core\Widgets\MenuButton */
        $menu = $dialog->createButton(UxonObject::fromArray(['widget_type' => 'MenuButton']));
        $menu->setCaption($this->getWorkbench()->getCoreApp()->getTranslator()->translate('GLOBAL.MODEL.ACTIONS'));
        $menu->setVisibility(EXF_WIDGET_VISIBILITY_PROMOTED);
        $menu->setInputWidget($table);
        foreach ($this->getMetaObject()->getActions()->getUsedInObjectBasket() as $a) {
            /* @var $button \exface\Core\Widgets\Button */
            $button = $menu->createButton();
            $button->setAction($a);
            $menu->addButton($button);
        }
        $dialog->addButton($menu);
        
        // Add actions menu to info dialog too
        // FIXME change to $info_button->getAction()->getDialogWidget()->getToolbarMain()->setIncludeObjectBasketActions(true);
        // because the input widget for the menu still is the table (see above)
        $info_button->getAction()->getWidget()->addButton($menu);
        
        return $dialog;
    }
}
?>