<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Widgets\DataConfigurator;

/**
 * 
 * @method DataConfigurator getWidget()
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
    public function registerFiltersWithApplyOnChange(AbstractJqueryElement $elementToRefresh = null)
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
                $filter_element->addOnChangeScript('setTimeout(function(){' . $elementToRefresh->buildJsRefresh() . '}, 50);');
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
                    $filters[] = $filterElement->buildJsConditionGetter();
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
                    $filters[] = $filterElement->buildJsConditionGetter($filter_value);
                }
            }
        }
        // Remove empty values
        $filters = array_filter($filters);
        
        if (empty($filters) === false  || empty($nestedGroups) === false) {
            $filter_group = '{operator: "AND", conditions: [' . implode(', ', $filters) . '], nested_groups: [' . implode(', ', $nestedGroups) . ']}';
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
     * 
     * @return string
     */
    protected function buildJsRefreshOnActionEffect() : string
    {
        if ($this->getWidget()->getWidgetConfigured()->hasAutorefreshData() === false) {
            return '';
        }
        $effectedAliases = [$this->getMetaObject()->getAliasWithNamespace()];
        foreach ($this->getWidget()->getDataWidget()->getColumns() as $col) {
            if (! $col->isBoundToAttribute()) {
                continue;
            }
            $attr = $col->getAttribute();
            if ($attr->getRelationPath()->isEmpty()) {
                continue;
            }
            foreach ($attr->getRelationPath()->getRelations() as $rel) {
                $effectedAliases[] = $rel->getLeftObject()->getAliasWithNamespace();
                $effectedAliases[] = $rel->getRightObject()->getAliasWithNamespace();
            }
        }
        foreach ($this->getWidget()->getFilters() as $filter) {
            if (! $filter->isBoundToAttribute()) {
                continue;
            }
            $attr = $filter->getAttribute();
            if ($attr->isRelation()) {
                $effectedAliases[] = $attr->getRelation()->getRightObject()->getAliasWithNamespace();   
            }
            if ($attr->getRelationPath()->isEmpty()) {
                continue;
            }
            foreach ($attr->getRelationPath()->getRelations() as $rel) {
                $effectedAliases[] = $rel->getLeftObject()->getAliasWithNamespace();
                $effectedAliases[] = $rel->getRightObject()->getAliasWithNamespace();
            }
        }
        $effectedAliasesJs = json_encode(array_values(array_unique($effectedAliases)));
        $actionperformed = AbstractJqueryElement::EVENT_NAME_ACTIONPERFORMED;
        return <<<JS

$( document ).off( "{$actionperformed}.{$this->getId()}" );
$( document ).on( "{$actionperformed}.{$this->getId()}", function( oEvent, oParams ) {
    var oEffect = {};
    var aUsedObjectAliases = {$effectedAliasesJs};
    var sConfiguredWidgetId = "{$this->getWidget()->getDataWidget()->getId()}";
    var fnRefresh = function() {
        {$this->buildJsRefreshConfiguredWidget(true)}
    };
    
    // Avoid errors if widget was removed already
    if ($('#{$this->getFacade()->getElement($this->getWidget()->getWidgetConfigured())->getId()}').length === 0) {
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
        if (aUsedObjectAliases.indexOf(oEffect.effected_object) !== -1) {
            // refresh immediately if directly affected or delayed if it is an indirect effect
            if (oEffect.effected_object === '{$this->getWidget()->getMetaObject()->getAliasWithNamespace()}') {
                fnRefresh();
            } else {
                setTimeout(fnRefresh, 100);
            }
            return;
        }
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
        return parent::buildJsResetter() . ';' . $this->getFacade()->getElement($this->getWidget()->getDataWidget())->buildJsRefresh();
    }
}