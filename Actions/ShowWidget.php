<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iShowWidget;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Actions\iPrefillWidget;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Actions\iReferenceWidget;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\ResultWidgetInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Actions\Traits\iPrefillWidgetTrait;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;

/**
 * The ShowWidget action is the base for all actions, that render widgets.
 * 
 * @author Andrej Kabachnik
 *        
 */
class ShowWidget extends AbstractAction implements iShowWidget, iPrefillWidget, iReferenceWidget
{
    use iPrefillWidgetTrait;
    
    private $widget = null;

    private $widget_uxon = null;

    private $widget_id = null;

    private $page_alias = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::EXTERNAL_LINK);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     * 
     * @return ResultWidgetInterface
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        // Try to instantiate a widget from the action's description
        $widget = $this->getWidget();
        
        // If the action does not have a widget defined, we can get it from the task if the task was
        // sent from a page and either has an explicit trigger widget or the page has a root widget (= is not empty).
        if ($widget === null && $task->isTriggeredOnPage() && ($task->isTriggeredByWidget() || ! $task->getPageTriggeredOn()->isEmpty())) {
            $widget = $task->getWidgetTriggeredBy();
        }
        
        if ($widget) {
            // TODO copy the widget before prefill because otherwise the action cannot hanlde more than one task!
            $widget = $this->prefillWidget($task, $widget);
            return ResultFactory::createWidgetResult($task, $widget);
        } else {
            return ResultFactory::createEmptyResult($task);
        }
    }

    /**
     * Returns the widget, that this action will show.
     * 
     * FIXME Currently this will even return a widget if the action links to another page.
     * This means, that all linked pages will be loaded when searching for a widget id -
     * and they will be searched too!!!
     * 
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\iShowWidget::getWidget()
     */
    public function getWidget()
    {
        if (is_null($this->widget)) {
            switch (true) {
                case $this->getWidgetUxon():
                    $this->widget = WidgetFactory::createFromUxon($this->getWidgetDefinedIn()->getPage(), $this->getWidgetUxon(), ($this->isDefinedInWidget() ? $this->getWidgetDefinedIn() : null), $this->getDefaultWidgetType());
                    break;
                case $this->widget_id && ! $this->page_alias:
                    $this->widget = $this->getWidgetDefinedIn()->getPage()->getWidget($this->widget_id);
                    break;
                case $this->page_alias && ! $this->widget_id:
                    // TODO this causes problems with simple links to other pages, as the action attempts to load them here...
                    // $this->widget = $this->getApp()->getWorkbench()->ui()->getPage($this->page_alias)->getWidgetRoot();
                    break;
                case $this->page_alias && $this->widget_id:
                    $this->widget = $this->getPage()->getWidget($this->widget_id);
                    break;
            }
        }
        return $this->widget;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iShowWidget::getDefaultWidgetType()
     */
    public function getDefaultWidgetType() : ?string
    {
        return null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iShowWidget::isWidgetDefined()
     */
    public function isWidgetDefined() : bool
    {
        try {
            $widget = $this->getWidget();
        } catch (\Throwable $e) {
            return false;
        }
        
        return is_null($widget) ? false : true;
    }

    /**
     * Defines the widget to be shown.
     * 
     * By default, the widget is based on the object of the action. Use the
     * `object_alias` widget property to specify another object - in this
     * case the workbench will try to find relations between this object and
     * the input data in order to use the latter. Alternatively you can use 
     * an `input_mapper` to specify, how input data is used.
     * 
     * @uxon-property widget
     * @uxon-type \exface\Core\Widgets\Container
     * @uxon-template {"widget_type": ""}
     * 
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\iShowWidget::setWidget()
     */
    public function setWidget($widget_or_uxon_object) : iShowWidget
    {
        if ($widget_or_uxon_object instanceof WidgetInterface) {
            $widget = $widget_or_uxon_object;
        } elseif ($widget_or_uxon_object instanceof UxonObject) {
            $this->setWidgetUxon($widget_or_uxon_object);
            $widget = null;
        } else {
            throw new ActionConfigurationError($this, 'Action "' . $this->getAlias() . '" expects the parameter "widget" to be either an instantiated widget or a valid UXON widget description object!', '6T91H2S');
        }
        $this->widget = $widget;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iReferenceWidget::getWidgetId()
     */
    public function getWidgetId()
    {
        if ($this->getWidget()) {
            return $this->getWidget()->getId();
        } else {
            return $this->widget_id;
        }
    }
    
    /**
     * Specifies the id of the widget to be shown. If not set, the main widget of the
     * page will be used.
     * 
     * @uxon-property widget_id
     * @uxon-type uxon:$..id
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iReferenceWidget::setWidgetId()
     */
    public function setWidgetId($value)
    {
        $this->widget_id = $value;
        return $this;
    }

    /**
     * ShowWidget needs some kind of widget representation in UXON in order to be recreatable from the UXON object.
     * TODO Currently the widget is represented by widget_id and page_alias and there is no action widget UXON saved here. This won't work for generated widgets!
     * 
     * @see \exface\Core\Interfaces\Actions\ActionInterface::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('widget_id', $this->getWidgetId());
        $uxon->setProperty('page_alias', $this->page_alias ? $this->page_alias : $this->getWidgetDefinedIn()->getPage()->getAliasWithNamespace());
        $uxon->setProperty('prefill_with_filter_context', $this->getPrefillWithFilterContext());
        $uxon->setProperty('prefill_with_input_data', $this->getPrefillWithInputData());
        if ($this->hasPrefillDataPreset()) {
            $uxon->setProperty('prefill_data_sheet', $this->getPrefillDataPreset()->exportUxonObject());
        }
        return $uxon;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iReferenceWidget::getPage()
     */
    public function getPage()
    {
        if ($this->isWidgetDefined()) {
            return $this->getWidget()->getPage();
        }
        return UiPageFactory::createFromModel($this->getWorkbench(), $this->page_alias);
    }
    
    public function getPageAlias()
    {
        return $this->page_alias;
    }

    /**
     * The alias of the page to get the widget from.
     * 
     * Widget links accept the internal UIDs, the namespaced alias as well as 
     * the CMS-page ids here because the users do not really know the difference
     * and will attempt to specify the id, they see first. Since most CMS show
     * their internal ids, that typically are not UUIDs, we just allow both ids
     * here.
     * 
     * @uxon-property page_alias
     * @uxon-type metamodel:page
     * 
     * @param string $value
     * @return iReferenceWidget
     */
    public function setPageAlias($value)
    {
        $this->page_alias = $value;
        return $this;
    }
    
    /**
     * 
     * @param UxonObject|string $uxon_object_or_string
     * @return \exface\Core\Actions\ShowWidget
     */
    protected function setWidgetUxon($uxon_object_or_string)
    {
        $this->widget_uxon = UxonObject::fromAnything($uxon_object_or_string);
        return $this;
    }
    
    /**
     * 
     * @return UxonObject
     */
    protected function getWidgetUxon()
    {
        return $this->widget_uxon;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::isTriggerWidgetRequired()
     */
    public function isTriggerWidgetRequired() : ?bool
    {
        return $this->widget_uxon === null && $this->widget_id === null && $this->page_alias === null;
    }
    
    /**
     * {@inheritdoc}
     * @see iPrefillWidget::getPrefillWithDataFromWidgetLink()
     */
    public function getPrefillWithDataFromWidgetLink()
    {
        return $this->prefill_with_data_from_widget_link;
    }
    
    /**
     * If a widget link is defined here, the prefill data for this action will
     * be taken from that widget link and not from the input widget.
     *
     * The value can be either a string ([page_alias]widget_id!optional_column_id)
     * or a widget link defined as an object.
     *
     * @uxon-property prefill_with_data_from_widget_link
     * @uxon-type \exface\Core\CommonLogic\WidgetLink
     *
     * @param string|UxonObject|WidgetLinkInterface $string_or_widget_link
     * @return \exface\Core\Actions\ShowWidget
     */
    public function setPrefillWithDataFromWidgetLink($string_or_widget_link) : iShowWidget
    {
        if ($string_or_widget_link) {
            $this->prefill_with_data_from_widget_link = WidgetLinkFactory::createFromWidget($this->getWidgetDefinedIn(), $string_or_widget_link);
            $this->setPrefillWithPrefillData(true);
        }
        return $this;
    }
}