<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Model\Object;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Factories\UiPageFactory;

/**
 * Fetches meta object instances stored in the object basket of the specified context_scope (by default, the window scope)
 *
 * @author Andrej Kabachnik
 *        
 */
class ObjectBasketFetch extends ObjectBasketAdd
{

    const OUTPUT_TYPE_JSON = 'JSON';

    const OUTPUT_TYPE_DIALOG = 'DIALOG';

    private $output_type = null;

    protected function perform()
    {
        if ($this->getOutputType() == static::OUTPUT_TYPE_DIALOG) {
            if ($this->getTemplate()->getRequestObjectId()) {
                $meta_object = $this->getWorkbench()->model()->getObject($this->getTemplate()->getRequestObjectId());
            }
            $this->setResult($this->buildDialog($meta_object));
        } else {
            $this->setResult($this->getFavoritesJson());
        }
    }

    protected function getFavoritesJson()
    {
        $result = array();
        foreach ($this->getContext()->getFavoritesAll() as $data_sheet) {
            $result[] = array(
                'object_id' => $data_sheet->getMetaObject()->getId(),
                'object_name' => $data_sheet->getMetaObject()->getName(),
                'instance_counter' => $data_sheet->countRows()
            );
        }
        return json_encode($result);
    }

    protected function buildDialog(Object $meta_object)
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
        $button->getAction()->setReturnBasketContent(true);
        $dialog->addButton($button);
        
        /*
         * IDEA delegate dialog rendering to ShowDialog action. Probably need to override getResultOutput in this case...
         * $action = $this->getApp()->getAction('ShowDialog');
         * $action->setTemplateAlias($this->getTemplate()->getAliasWithNamespace());
         * $action->setWidget($dialog);
         * return $action->getResult();
         */
        
        return $this->getTemplate()->drawHeaders($dialog) . $this->getTemplate()->draw($dialog);
    }

    public function getOutputType()
    {
        if (is_null($this->output_type)) {
            if ($type = $this->getWorkbench()->getRequestParam('output_type')) {
                $this->setOutputType($type);
            } else {
                $this->output_type = static::OUTPUT_TYPE_JSON;
            }
        }
        return $this->output_type;
    }

    public function setOutputType($value)
    {
        $const = 'static::OUTPUT_TYPE_' . mb_strtoupper($value);
        if (! defined($const)) {
            throw new ActionConfigurationError($this, 'Invalid value "' . $value . '" for option "output_type" of action "' . $this->getAliasWithNamespace() . '": use "JSON" or "DIALOG"!');
        }
        $this->output_type = constant($const);
        return $this;
    }
}
?>