<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Exceptions\Widgets\WidgetPropertyNotSetError;

/**
 * This interface defines, how widgets can support optional lazy (asynchronous) loading.
 * 
 * Lazy loading widgets trigger a secondary action asynchronously when being instantiated.
 * This action actually does the lazy loading. Depending on the widget and, the action can 
 * load different parts of the widget: e.g. the data in lazy data widgets or the widget's
 * children for panels or dialogs.
 * 
 * Lazy loading can be switched on or off using the `lazy_loading` UXON property. The default
 * setting depends on the facade used.
 * 
 * The lazy loading action can be fully configured in the `lazy_loading_action` property.
 * 
 * Additionally widgets, that depend upon each other can be put into lazy loading groups 
 * to synchronize their behavior and avoid unnecessarry calls to the back-end - see
 * description of `lazy_loading_group_id` for more details.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iSupportLazyLoading extends iTriggerAction
{

    /**
     * 
     * @param bool $default
     * @return bool
     */
    public function getLazyLoading($default = true) : bool;

    /**
     * 
     * @param boolean $value
     * @return iSupportLazyLoading
     */
    public function setLazyLoading(bool $value) : iSupportLazyLoading;
    
    /**
     * 
     * @throws WidgetPropertyNotSetError if no action can be determined
     * @return ActionInterface
     */
    public function getLazyLoadingAction() : ActionInterface;

    /**
     * 
     * @return string|NULL
     */
    public function getLazyLoadingGroupId() : ?string;
}