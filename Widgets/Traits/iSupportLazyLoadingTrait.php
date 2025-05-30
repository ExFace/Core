<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Factories\ActionFactory;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Exceptions\Widgets\WidgetPropertyNotSetError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\Exceptions\Widgets\WidgetLogicError;

/**
 * This trait helps implement the iSupportLazyLoading widget interface.
 * 
 * @author Andrej Kabachnik
 *
 */
trait iSupportLazyLoadingTrait {
    
    private $lazy_loading = null;
    
    private $lazy_loading_action_uxon = null;
    
    private $lazy_loading_action = null;
    
    private $lazy_loading_group_id = null;
    
    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoading()
     */
    public function getLazyLoading($default = true) : bool
    {
        return $this->lazy_loading ?? $default;
    }
    
    /**
     * Makes data values get loaded asynchronously in background if the facade supports it (i.e.
     * via AJAX).
     *
     * @uxon-property lazy_loading
     * @uxon-type boolean
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoading()
     */
    public function setLazyLoading(bool $value) : iSupportLazyLoading
    {
        $this->lazy_loading = $value;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoadingGroupId()
     */
    public function getLazyLoadingGroupId() : ?string
    {
        return $this->lazy_loading_group_id;
    }
    
    /**
     * Assigns this widget to the specified lazy loading group.
     *
     * A lazy loading group is a group of widgets, which is designed to always have a
     * consistent state. Additionally the number of POST-requests, necessary to update
     * this group is optimized. A good example for a lazy loading group are the widgets
     * for `STYLE` (ARTICLE), `ARTICLE_SUPPLIER`, `COLOR`, `SIZING` and `SELLING_CODE` 
     * which can be added to a group 'article_depend_control'. Then the five widgets 
     * will always show a consistent state i.e. if a color is selected only Styles, 
     * Article Supplier, Sizings and Selling Codes matching this color are shown.
     *
     * A lazy loading group is created by assigning the same lazy_loading_group_id to all
     * elements of the group. Additionally every element of the group needs a filter-ref-
     * erence to every other element of the group i.e. color will have filter-references
     * for Style, Article Supplier, Sizing and Selling Code. An example for a lazy
     * loading group can be found in the consumer complaint dialog.
     *
     * The concrete implementation of the `lazy_loading_group` is done in the individual
     * facades, consequently the behavior of such a group might vary in the different
     * facades.
     *
     * @uxon-property lazy_loading_group_id
     * @uxon-type string
     * 
     * @param string $value
     * @return \exface\Core\Interfaces\Widgets\iSupportLazyLoading
     */
    protected function setLazyLoadingGroupId(string $value) : iSupportLazyLoading
    {
        $this->lazy_loading_group_id = $value;
        return $this;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoadingAction()
     */
    public function getLazyLoadingAction() : ActionInterface
    {
        if ($this->lazy_loading_action === null) {
            $uxon = $this->getLazyLoadingActionUxon();
            if ($uxon->isEmpty() || false === $uxon->hasProperty('alias')) {
                throw new WidgetPropertyNotSetError($this, 'Lazy loading action not configured for widget ' . $this->getWidgetType() . '!');
            }
            $this->lazy_loading_action = ActionFactory::createFromUxon($this->getWorkbench(), $uxon, $this);
        } 
        return $this->lazy_loading_action;
    }
    
    /**
     * The action to call when lazy loading the content of the widget.
     * 
     * This property only has effect if `lazy_loading` is set to `true`.
     * 
     * @uxon-property lazy_loading_action
     * @uxon-type \exface\Core\CommonLogic\AbstractAction
     * @uxon-template {"alias": ""}
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @throws \exface\Core\Exceptions\Widgets\WidgetLogicError
     * @return \exface\Core\Interfaces\Widgets\iSupportLazyLoading
     */
    public function setLazyLoadingAction(UxonObject $uxon) : iSupportLazyLoading
    {
        if ($this->lazy_loading_action !== null) {
            throw new WidgetLogicError($this, 'Cannot set lazy_loading_action for ' . $this->getWidgetType() . ': the action has been already instantiated!');
        }
        $this->lazy_loading_action_uxon = $uxon;
        return $this;
    }
    
    /**
     * Returns the UXON description of the lazy loading action as defined in the widget
     * property lazy_loading_action.
     * 
     * @return UxonObject
     */
    protected function getLazyLoadingActionUxon() : UxonObject
    {
        return $this->lazy_loading_action_uxon ?? $this->getLazyLoadingActionUxonDefault();
    }
    
    /**
     * @deprecated use $this->getLazyLoadingAction() instead!
     * @return string
     */
    public function getLazyLoadingActionAlias() : string
    {
        return $this->getLazyLoadingAction()->getAliasWithNamespace();
    }
    
    /**
     * Returns the UXON description of the lazy loading action to be used
     * for this widget by default (if nothing was specified in the widget
     * UXON).
     * 
     * Override this method to give a widget a default lazy loading action
     * (see InputCombo for an example).
     * 
     * @return UxonObject
     */
    protected function getLazyLoadingActionUxonDefault() : UxonObject
    {
        return new UxonObject([
            'alias' => 'exface.Core.ReadData'
        ]);
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iTriggerAction::getAction()
     */
    public function getAction()
    {
        /* There is some uncertainity about when to return null here. If null is returned,
         * the lazy loading action _cannot_ be called from a facade via task, because tasks
         * will ask their widgets if they really can trigger such actions - see 
         * `GenericTask::getAction()`. Sometimes it is important to get the lazy loading data
         * even if lazy loading is off. For example, non-lazy InputComboTable might need to
         * refresh their data when it is effected by an action or with a prefill. This is
         * particularly important in AJAX facades with view caching like UI5: since non-lazy
         * data is part of the view, it would be cached forever even if the underlying data
         * is changed.
         * 
         * Right now, the lazy loading action is always available unless lazy loading is off
         * AND the action's object is not readable.
         */ 
        $action = $this->getLazyLoadingAction();
        if ($this->getLazyLoading() === false && ($action === null || $action->getMetaObject()->isReadable() === false)) {
            return null;
        }
        return $this->getLazyLoadingAction();
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iTriggerAction::hasAction()
     */
    public function hasAction() : bool
    {
        return $this->getAction() ? true : false;
    }
}