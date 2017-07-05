<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Model\Object;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Exceptions\Actions\ActionInputInvalidObjectError;
use exface\Core\CommonLogic\Contexts\ContextActionTrait;
use exface\Core\Widgets\AbstractWidget;

/**
 * Displays a popup-table with all instances of a meta object in the object basket.
 * 
 * Using the context_scope and context_type UXON properties the action can be 
 * used to fetch object instances from different baskets. 
 *
 * @author Andrej Kabachnik
 *        
 */
class ObjectBasketShowDialog extends ShowDialog
{
    use ContextActionTrait; 
    
    protected function init()
    {
        parent::init();
        $this->setInputRowsMax(1);
        $this->setInputRowsMin(1);
        $this->setContextType('ObjectBasket');
    }

    /**
     * When the action is performed, the empty ObjectBasketDialog created by
     * createDialogWidget() is assigned the meta object from the input data
     * sheet and filled with instances of this object stored in the object 
     * basket.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowWidget::perform()
     */
    protected function perform()
    {
        
        if ($this->getInputDataSheet() && $this->getInputDataSheet()->getMetaObject()->is('exface.Core.CONTEXT_BASE_OBJECT')) {
            $meta_object = $this->getWorkbench()->model()->getObject($this->getInputDataSheet()->getCellValue('ID', 0));
        } else {
            throw new ActionInputInvalidObjectError($this, 'Missing or invalid input data object: expecting exface.Core.CONTEXT_BASE_OBJECT or derivatives!');
        }
        
        $this->getDialogWidget()->setMetaObject($meta_object);
        $table = $this->getDialogWidget()->getWidgets()[0];
        $table->setMetaObject($meta_object);
        $table->prefill($this->getContext()->getFavoritesByObject($meta_object));
        
        // Add action buttons
        foreach ($meta_object->getActions()->getUsedInObjectBasket() as $a) {
            /* @var $button \exface\Core\Widgets\Button */
            $button = WidgetFactory::create($this->getDialogWidget()->getPage(), $this->getDialogWidget()->getButtonWidgetType(), $this->getDialogWidget());
            $button->setAction($a);
            $button->setAlign(EXF_ALIGN_LEFT);
            $button->setInputWidget($table);
            $this->getDialogWidget()->addButton($button);
        }
        
        parent::perform();
    }

    /**
     * By default the ObjectBasketDialog contains an empty table with a remove-button.
     * The actual contents can only be rendered when the action is actually
     * performed as only at that time we know, which object we are interested in.
     * 
     * The empty dialog must be created before the action is performed in order
     * for the containing page to find it's widgets. 
     * 
     * This means, that all buttons, that do not depend on the meta object shown
     * should be added here while buttons that only work with specific object
     * must be created in the perform(). Those buttons cannot be found by id
     * references though, so basically only model actions or actions with default
     * parameters are supported.     * 
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowDialog::createDialogWidget()
     */
    protected function createDialogWidget(AbstractWidget $contained_widget = null)
    {
        try {
            $page = $this->getCalledOnUiPage();
        } catch (\Throwable $e) {
            $page = UiPageFactory::createEmpty($this->getWorkbench()->ui(), 0);
        }
        /* @var $dialog \exface\Core\Widgets\Dialog */
        $dialog = WidgetFactory::create($page, 'Dialog', $this->getCalledByWidget());
        $dialog->setId('object_basket');
        $dialog->setCaption($this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.OBJECTBASKET'));
        $dialog->setLazyLoading(false);
        
        /* @var $table \exface\Core\Widgets\DataTable */
        $table = WidgetFactory::create($dialog->getPage(), 'DataTable', $dialog);
        $table->setLazyLoading(false);
        $table->setPaginate(false);
        $table->setHideToolbarBottom(true);
        $table->setMultiSelect(true);
        $table->setMultiSelectAllSelected(true);
        $dialog->addWidget($table);
        
        // Add remove button
        $button = WidgetFactory::create($dialog->getPage(), 'DialogButton', $dialog);
        $button->setActionAlias('exface.Core.ObjectBasketRemove');
        $button->setInputWidget($table);
        $button->setAlign(EXF_ALIGN_LEFT);
        $button->getAction()->setContextScope($this->getContextScope())->setContextType($this->getContextType());
        $dialog->addButton($button);
        
        return $dialog;
    }
}
?>