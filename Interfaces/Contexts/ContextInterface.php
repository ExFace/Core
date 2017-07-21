<?php
namespace exface\Core\Interfaces\Contexts;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Widgets\Container;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Interfaces\AppInterface;

/**
 * A context is a container for stateful data, that needs to live longer, than
 * a single request. 
 * 
 * Contexts are similar to applications in the Windows system
 * tray: they are allways accessible via an icon in the context bar, they
 * maintain their own internal state and offer quick access to common features.
 * 
 * Every context can be placed in one of the available context scopes. Each
 * scope has it's own lifetime for it's contexts. So, if a context is placed in
 * the window scope, it lives as long as the browser window exists and is lost
 * if the user closes the window. A context in the user scope on the other hand
 * exists as long as the user account is not removed from the system.
 * 
 * Apart from a few contexts shipped with the plattform, app developers can
 * create their own custom contexts: just like Windows developers can place
 * their own icons in the system tray. 
 * 
 * Administrators and users have the possibility to customize the context bar
 * in the system or user configuration by changing the order and visibility
 * settings for every context icon. 
 * 
 * @author Andrej Kabachnik
 *
 */
interface ContextInterface extends AliasInterface, iCanBeConvertedToUxon, ExfaceClassInterface
{
    
    const CONTEXT_BAR_SHOW_ALLWAYS = 'show_allways';
    const CONTEXT_BAR_SHOW_IF_NOT_EMPTY = 'show_if_not_empty';
    const CONTEXT_BAR_HIDE_ALLWAYS = 'hide_allways';
    const CONTEXT_BAR_DISABED = 'disabled';
    const CONTEXT_BAR_DEFAULT = 'default';
    const CONTEXT_BAR_EMPHASIZED = 'emphasized';

    /**
     * Returns the scope of this speicific context
     *
     * @return ContextScopeInterface
     */
    public function getScope();

    /**
     * Sets the scope for this specific context
     *
     * @param AbstractContextScope $context_scope            
     * @return AbstractContext
     */
    public function setScope(ContextScopeInterface $context_scope);

    /**
     * Returns the default scope for this type of context.
     *
     * @return ContextScopeInterface
     */
    public function getDefaultScope();

    /**
     * Returns the alias (name) of the context - e.g. "FilterContext" for the exface.Core.FilterContext, etc.
     *
     * @return string
     */
    public function getAlias();
    
    
    /**
     * Returns a string indicating the current state of the context.
     * 
     * Most templates will show the indicator next to the context button or
     * in a "badge" or label within it. Indicators are typically counters (e.g.
     * "3" if there are three items in the favorites context), abbrevations or
     * symbols/pictograms.
     * 
     * @return string
     */
    public function getIndicator();
    
    /**
     * Changes the indicator of this context.
     *
     * @param string $indicator
     * @return ContextInterface
     */
    public function setIndicator($indicator);
    
    /**
     * Returns the color code for the indicator. Colors::DEFAULT by default.
     *
     * @return string
     */
    public function getColor();
    
    /**
     * Changes the color of the indicator of this context.
     * 
     * Any color code supported by the templates can be used, althogh the
     * core colors specified in the Colors::CONSTANTS are recommended.
     *
     * @param string $indicator
     * @return ContextInterface
     */
    public function setColor($value);
    
    /**
     * Returns the visibility value for this context in the context bar.
     *
     * @return string
     */
    public function getVisibility();
    
    /**
     * Sets the context_bar_visibility of the context.
     *
     * Possible values:
     * - show_allways
     * - show_if_not_emptyy
     * - hide_allways
     * - disabled
     *
     * @param string $context_bar_visibility
     * @return ContextInterface
     */
    public function setVisibility($value);
    
    /**
     * Returns TRUE if the context has no data and FALSE otherwise
     *
     * @return boolean
     */
    public function isEmpty();
    
    /**
     * Fills a given container widget with context specific controls to be 
     * used in the context bar popup.
     * 
     * Such a popup container will typically contain a Menu widget or a DataList
     * depending on the context: contexts like favorites or notifications will
     * show a DataList with their contents while the DebugContext will display
     * a list of buttons (a Menu widget).
     * 
     * @param Container $container
     * @return \exface\Core\Widgets\Container
     */
    public function getContextBarPopup(Container $container);
    
    /**
     * Returns the name of the icon for this context.
     * 
     * @return string
     */
    public function getIcon();
    
    /**
     * Sets the name of the icon to be used for this context. 
     * 
     * @param string $icon
     * @return ContextInterface
     */
    public function setIcon($icon);
    
    /**
     * Returns a human readable (translated!) name of the context
     * 
     * @return string
     */
    public function getName();
    
    /**
     * Changes the name of this context. Make sure, it is translated correctly!
     * 
     * @param string $name
     * @return ContextInterface
     */
    public function setName($name);
    
    /**
     * Returns a running instance of the app, the context belongs to
     * 
     * @return AppInterface
     */
    public function getApp();
}
?>