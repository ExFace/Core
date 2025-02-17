<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iHaveFilters;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\CommonLogic\UxonObject;

/**
 * Defines how to apply a filter to other objects than its own
 * 
 * E.g. if you have an `APP` filter on `exface.Core.PAGE`, you can also apply it to 
 * `exface.Core.PAGE_GROUP`, but in that case it would not be `APP`, but rather 
 * `PAGE__APP`. This widget part allows to define these mappings.
 * 
 * ## Example:
 * 
 * ```json
 *  {
 *      "object_alias": "exface.Core.PAGE",
 *      "widget_type": "Dashbaord",
 *      "filters": [
 *          {"attribtue_alias": "APP"}
 *      ],
 *      "filters_apply_to": {
 *          "APP": [
 *              {
 *                  "object_alias": "exface.Core.PAGE_GROUP",
 *                  "filter": {
 *                      "attribute_alias": "PAGE__APP",
 *                      "required": true
 *                  }
 *              },
 *              {
 *                  "object_alias": "exface.Core.OBJECT",
 *                  "disabled": true,
 *                  "filter": {
 *                      "attribute_alias": "APP"
 *                  }
 *              }
 *          ]
 *      }
 *  }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *        
 */
class DataFilterMapping implements WidgetPartInterface
{
    use ImportUxonObjectTrait;
    
    private $fromWidget = null;

    private $fromFilterAlias = null;

    private $objectAlias = null;

    private $disabled = false;

    private $disabledForWidgetIds = [];

    private $filterUxon = null;
    
    public function __construct(iHaveFilters $widget, string $fromFilterAlias, UxonObject $uxon = null)
    {
        $this->fromWidget = $widget;
        $this->fromFilterAlias = $fromFilterAlias;
        if ($uxon !== null) {
            $this->importUxonObject($uxon);
        }
    }
    
    public function getMetaObject() : MetaObjectInterface
    {
        return $this->fromWidget->getMetaObject();
    }
    
    /**
     * 
     * @return iHaveFilters
     */
    public function getFilteringWidget() : iHaveFilters
    {
        return $this->fromWidget;
    }

    public function getSourceFilterAttributeAlias() : string
    {
        return $this->fromFilterAlias;
    }

    /**
     * Alias of the target object - the filter will have effect on that object then
     * 
     * @uxon-property object_alias
     * @uxon-type metamodel:object
     * @uxon-required true
     * 
     * @param string $aliasWithNamespace
     * @return \exface\Core\Widgets\Parts\DataFilterMapping
     */
    protected function setObjectAlias(string $aliasWithNamespace) : DataFilterMapping
    {
        $this->objectAlias = $aliasWithNamespace;
        return $this;
    }

    /**
     * 
     * @return string
     */
    public function getTargetObjectAlias() : string
    {
        return $this->objectAlias;
    }

    /**
     * Set to TRUE to make the filter NOT have effect on the target object.
     * 
     * @uxon-property disabled
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return \exface\Core\Widgets\Parts\DataFilterMapping
     */
    protected function setDisabled(bool $trueOrFalse) : DataFilterMapping
    {
        $this->disabled = $trueOrFalse;
        return $this;
    }

    /**
     * Only disable this filter mapping for certain ids of data widgets in the dashboard
     * 
     * @uxon-property disabled_for_widget_ids
     * @uxon-type array
     * @uxon-template [""]
     * 
     * @param \exface\Core\CommonLogic\UxonObject $arrayOfWidgetIds
     * @return DataFilterMapping
     */
    protected function setDisabledForWidgetIds(UxonObject $arrayOfWidgetIds) : DataFilterMapping
    {
        $this->disabledForWidgetIds = $arrayOfWidgetIds->toArray();
        return $this;
    }

    /**
     * 
     * @return bool
     */
    public function isDisabled(iHaveFilters $dataWidget) : bool
    {
        if ($this->disabled === true) {
            return true;
        }
        foreach ($this->disabledForWidgetIds as $id) {
            if ($dataWidget->getId(false) === $id) {
                return true;
            }
        }
        return $this->disabled;
    }

    /**
     * Definition of the filter to be used on the target object
     * 
     * @uxon-property filter
     * @uxon-type \exface\Core\Widgets\Filter
     * @uxon-template {"attribute_alias": ""}
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return \exface\Core\Widgets\Parts\DataFilterMapping
     */
    protected function setFilter(UxonObject $uxon) : DataFilterMapping
    {
        $this->filterUxon = $uxon;
        return $this;
    }

    /**
     * 
     * @return \exface\Core\CommonLogic\UxonObject
     */
    public function getTargetFilterUxon() : UxonObject
    {
        return $this->filterUxon ?? new UxonObject();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPartInterface::getWidget()
     */
    public function getWidget(): WidgetInterface
    {
        return $this->fromWidget;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->getWidget()->getWorkbench();
    }

    /**
     * 
     * @return UxonObject
     */
    public function exportUxonObject()
    {
        return new UxonObject([]);
    }
}