<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Interfaces\Actions\ActionInterface;

/**
 * 
 * @method \exface\Core\Widgets\DataConfigurator getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
trait JqueryDataConfiguratorTrait 
{
    protected function init()
    {
        $result = parent::init();
        
        $this->registerFiltersWithApplyOnChange();
        
        return $result;
    }
    
    /**
     * 
     * @param AbstractJqueryElement $elementToRefresh
     */
    public function registerFiltersWithApplyOnChange(AbstractJqueryElement $elementToRefresh = null, int $waitMs = 50)
    {
        $widget = $this->getWidget();
        foreach ($widget->getFilters() as $filter) {
            // For each filter with auto-apply trigger a refresh once the value of the filter changes.
            if ($filter->getApplyOnChange()) {
                $elementToRefresh = is_null($elementToRefresh) ? $this->getFacade()->getElement($widget->getWidgetConfigured()) : $elementToRefresh;
                $filter_element = $this->getFacade()->getElement($filter);
                // Wrap the refresh in setTimeout() to make sure multiple filter can get their values before
                // one of the actually triggers the refresh. This also solved a strange bug, where the refresh
                // did not start with the first value change, but only with the second one an onwards.
                $filter_element->addOnChangeScript("setTimeout(function(){ {$elementToRefresh->buildJsRefresh()} }, {$waitMs});");
            }
        }
        return;
    }
    
    /**
     * The data JS-object of a configurator widget contains filters, sorters, etc., that are
     * required to read the corresponding data.
     * 
     * NOTE: since data widgets are sometimes used within other widgets (i.e. InputComboTable)
     * without being really rendered, the data getter can be operate in two modes: rendered
     * and unrendered. While the former relies on the current value of rendered elements,
     * the latter will only include values defined in UXON. 
     * 
     * @param ActionInterface $action
     * @param boolean $unrendered
     * @return string
     */
    public function buildJsDataGetter(ActionInterface $action = null, bool $unrendered = false)
    {
        $widget = $this->getWidget();
        $filters = [];
        $nestedGroups = [];
        
        if (! $unrendered) {
            foreach ($widget->getFilters() as $filter) {
                $filterElement = $this->getFacade()->getElement($filter);
                if ($filter->hasCustomConditionGroup() === true) {
                    $nestedGroups[] = $filterElement->buildJsCustomConditionGroup();
                } else {
                    $filters[] = $filterElement->buildJsConditionGetter(null, $widget->getMetaObject());
                }
            }
        } else {
            foreach ($widget->getFilters() as $filter) {
                $filterElement = $this->getFacade()->getElement($filter);
                if ($link = $filter->getValueWidgetLink()) {
                    $linked_element = $this->getFacade()->getElement($link->getTargetWidget());
                    $filter_value = $linked_element->buildJsValueGetter($link->getTargetColumnId());
                } else {
                    $filter_value = '"' . $filter->getValue() . '"';
                }
                
                if ($filter->hasCustomConditionGroup() === true) {
                    $nestedGroups[] = $filterElement->buildJsCustomConditionGroup($filter_value);
                } else {
                    $filters[] = $filterElement->buildJsConditionGetter($filter_value, $widget->getMetaObject());
                }
            }
        }
        // Remove empty values
        $filters = array_filter($filters);
        $conditionsJs = '[' . implode(",\n", $filters) . ']';
        //$conditionsJs .= ".filter(function(oCond){return oCond.value !== null && oCond.value !== undefined && oCond.value !== '';})";
        if (empty($filters) === false  || empty($nestedGroups) === false) {
            $filter_group = '{operator: "AND", ignore_empty_values: true, conditions: ' . $conditionsJs . ', nested_groups: [' . implode(",\n", $nestedGroups) . ']}';
        } else {
            $filter_group = '';
        }
        return "{oId: '" . $widget->getMetaObject()->getId() . "'" . ($filter_group !== '' ? ", filters: " . $filter_group : "") . "}";
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsRefreshOnEnter()
    {
        // Use keyup() instead of keypress() because the latter did not work with jEasyUI combos.
        return <<<JS
        setTimeout(function(){
            $('#{$this->getId()}').find('input').keyup(function (ev) {
                var keycode = (ev.keyCode ? ev.keyCode : ev.which);
                if (keycode == '13') {
                    {$this->buildJsRefreshConfiguredWidget(false)};
                }
            })
        }, 10);

JS;
    }
    
    /**
     * Registers a jQuery custom event handler that refreshes the configured widget if effected by an action.
     * 
     * Returns JS code to register a listener on `document` for the custom jQuery event 
     * `actionperformed`. The listener will see if the widget configured is affected
     * by the event (e.g. by the action effects) and triggers a refresh on the widget.
     * 
     * By default the script does nothing if there is no DOM element matchnig the id of
     * the configured element. This check can be disabled by setting $onlyIfDomExists to false.
     * 
     * @param string $scriptJs
     * @param bool $onlyIfDomExists
     * @return string
     */
    protected function buildJsRegisterOnActionPerformed(string $scriptJs, bool $onlyIfDomExists = true) : string
    {
        $dataWidget = $this->getWidget()->getDataWidget();
        if ($dataWidget->hasAutorefreshData() === false) {
            return '';
        }
        $dataEl = $this->getFacade()->getElement($dataWidget);
        $onlyIfDomExistsJs = $onlyIfDomExists ? 'true' : 'false';
        $effectedAliases = [];
        foreach ($dataWidget->getMetaObjectsEffectingThisWidget() as $object) {
            if ($object->getAliasWithNamespace() !== null) {
                $effectedAliases[] = $object->getAliasWithNamespace();
            }
        }
        $effectedAliasesJs = json_encode(array_values(array_unique($effectedAliases)));
        $actionperformed = AbstractJqueryElement::EVENT_NAME_ACTIONPERFORMED;

        return <<<JS

$( document ).off( "{$actionperformed}.{$this->getId()}" );
$( document ).on( "{$actionperformed}.{$this->getId()}", function( oEvent, oParams ) {
    var oEffect = {};
    var bOnlyIfDomExists = {$onlyIfDomExistsJs};
    var aUsedObjectAliases = {$effectedAliasesJs};
    var sConfiguredWidgetId = "{$this->getWidget()->getDataWidget()->getId()}";
    var oDirectEffect, oIndirectEffect;
    var fnRefresh = function() {
        {$scriptJs}
    };

    // Avoid errors if widget was removed already
    if (bOnlyIfDomExists && $('#{$dataEl->getId()}').length === 0) {
        return;
    }

    if (oParams.refresh_not_widgets.indexOf(sConfiguredWidgetId) !== -1) {
        return;
    }

    if (oParams.refresh_widgets.indexOf(sConfiguredWidgetId) !== -1) {
        fnRefresh();
        return;
    }

    for (var i = 0; i < oParams.effects.length; i++) {
        oEffect = oParams.effects[i];
        if (oEffect.effected_object === '{$dataWidget->getMetaObject()->getAliasWithNamespace()}') {
            if (oDirectEffect === undefined || (oDirectEffect.handles_changes === false && oEffect.handles_changes === true)) {
                oDirectEffect = oEffect;
                if (oEffect.handles_changes === true) {
                    break;
                } else {
                    continue;
                }
            }
        }
        if (oIndirectEffect === undefined && aUsedObjectAliases.indexOf(oEffect.effected_object) !== -1) {
            oIndirectEffect = oEffect;
        }
    }
    // refresh immediately if directly affected or delayed if it is an indirect effect
    if (oDirectEffect !== undefined) {
        // If a directly affecting action saves our changes, reset them before refreshing
        if (oDirectEffect.handles_changes === true) {
            {$dataEl->buildJsDataSetter($dataEl->buildJsDataGetter())}
        }
        fnRefresh();
    } else if (oIndirectEffect !== undefined) {
        setTimeout(fnRefresh, 100);
    }
});

JS;
    }
    
    /**
     * 
     * @param bool $keepPagination
     * @return string
     */
    protected function buildJsRefreshConfiguredWidget(bool $keepPagination) : string
    {
        return $this->getFacade()->getElement($this->getWidget()->getWidgetConfigured())->buildJsRefresh($keepPagination);
    }
                
    /**
     * In a configurator, all filters must be validated before it's data can be used.
     * 
     * @see JqueryContainerTrait::getWidgetsToValidate()
     */
    protected function getWidgetsToValidate()
    {
        return $this->getWidget()->getFilters();
    }
    
    /**
     *
     * {@inheritdoc}
     * @see JqueryContainerTrait::buildJsResetter()
     */
    public function buildJsResetter() : string
    {
        return parent::buildJsResetter() . ';' 
            . $this->getFacade()->getElement($this->getWidget()->getDataWidget())->buildJsRefresh();
    }
}