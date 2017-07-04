<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Model\Object;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Exceptions\Actions\ActionInputInvalidObjectError;
use exface\Core\CommonLogic\Contexts\ContextActionTrait;

/**
 * Fetches meta object instances stored in the object basket of the specified context_scope (by default, the window scope)
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

    protected function perform()
    {
        
        if ($this->getInputDataSheet() && $this->getInputDataSheet()->getMetaObject()->is('exface.Core.CONTEXT_BASE_OBJECT')) {
            $meta_object = $this->getWorkbench()->model()->getObject($this->getInputDataSheet()->getCellValue('ID', 0));
        } else {
            throw new ActionInputInvalidObjectError($this, 'Missing or invalid input data object: expecting exface.Core.CONTEXT_BASE_OBJECT or derivatives!');
        }
        
        $this->setResult($this->createDialog($meta_object));
        
    }

    protected function createDialog(Object $meta_object)
    {
        try {
            $page = $this->getCalledOnUiPage();
        } catch (\Throwable $e) {
            $page = UiPageFactory::createEmpty($meta_object->getWorkbench()->ui(), 0);
        }
        /* @var $dialog \exface\Core\Widgets\Dialog */
        $dialog = WidgetFactory::create($page, 'Dialog');
        $dialog->setId('object_basket');
        $dialog->setMetaObject($meta_object);
        $dialog->setCaption($this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.OBJECTBASKET'));
        $dialog->setLazyLoading(false);
        
        /* @var $table \exface\Core\Widgets\DataTable */
        $table = WidgetFactory::create($dialog->getPage(), 'DataTable', $dialog);
        $table->setLazyLoading(false);
        $table->setPaginate(false);
        $table->setHideToolbarBottom(true);
        $table->setMultiSelect(true);
        $table->setMultiSelectAllSelected(true);
        $table->prefill($this->getContext()->getFavoritesByObject($meta_object));
        $dialog->addWidget($table);
        
        // Add action buttons
        foreach ($meta_object->getActions()->getUsedInObjectBasket() as $a) {
            /* @var $button \exface\Core\Widgets\Button */
            $button = WidgetFactory::create($dialog->getPage(), 'DialogButton', $dialog);
            $button->setAction($a);
            $button->setAlign(EXF_ALIGN_LEFT);
            $button->setInputWidget($table);
            $dialog->addButton($button);
        }
        
        // Add remove button
        $button = WidgetFactory::create($dialog->getPage(), 'DialogButton', $dialog);
        $button->setActionAlias('exface.Core.ObjectBasketRemove');
        $button->setInputWidget($table);
        $button->setAlign(EXF_ALIGN_LEFT);
        $dialog->addButton($button);
        
        /*
         * IDEA delegate dialog rendering to ShowDialog action. Probably need to override getResultOutput in this case...
         * $action = $this->getApp()->getAction('ShowDialog');
         * $action->setTemplateAlias($this->getTemplate()->getAliasWithNamespace());
         * $action->setWidget($dialog);
         * return $action->getResult();
         */
        
        return $dialog;
    }
}
?>