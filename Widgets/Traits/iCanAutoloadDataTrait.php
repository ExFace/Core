<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Interfaces\Widgets\iCanAutoloadData;

/**
 * This trait contains common methods to implement the iCanAutoloadData interface.
 * 
 * @author Andrej Kabachnik
 */
trait iCanAutoloadDataTrait {
    
    private $autoload_data = true;
    
    private $autoload_disabled_hint = null;
    
    private $autorefresh = true;
    
    /**
     * 
     * @see iCanAutoloadData::hasAutoloadData()
     */
    public function hasAutoloadData() : bool
    {
        return $this->autoload_data;
    }
    
    /**
     * Set to FALSE to prevent initial loading of data or TRUE (default) to enable it.
     *
     * NOTE: if autoload is disabled, the widget will show a message specified in the
     * `autoload_disabled_hint` property.
     *
     * @uxon-property autoload_data
     * @uxon-type boolean
     *
     * @see iCanAutoloadData::setAutoloadData()
     */
    public function setAutoloadData(bool $autoloadData) : iCanAutoloadData
    {
        $this->autoload_data = $autoloadData;
        return $this;
    }
    
    /**
     * Returns a text which can be displayed if initial loading is prevented.
     *
     * @see iCanAutoloadData::getAutoloadDisabledHint()
     */
    public function getAutoloadDisabledHint() : string
    {
        if ($this->autoload_disabled_hint === null) {
            return $this->translate('WIDGET.DATA.NOT_LOADED');
        }
        return $this->autoload_disabled_hint;
    }
    
    /**
     * Overrides the text shown if autoload_data is set to FALSE or required filters are missing.
     *
     * Use `=TRANSLATE()` to make the text translatable.
     *
     * @uxon-property autoload_disabled_hint
     * @uxon-type string|metamodel:formula
     * @uxon-translatable true
     *
     * @see iCanAutoloadData::setAutoloadDisabledHint()
     */
    public function setAutoloadDisabledHint(string $text) : iCanAutoloadData
    {
        $this->autoload_disabled_hint = $this->evaluatePropertyExpression($text);
        return $this;
    }
    
    /**
     * Set to FALSE to disable automatic refreshes of this widget after actions are performed.
     *
     * @uxon-property autorefresh_data
     * @uxon-type boolean
     * @uxon-default true
     *
     * @see iCanAutoloadData::setAutorefreshData()
     */
    public function setAutorefreshData(bool $trueOrFalse) : iCanAutoloadData
    {
        $this->autorefresh = $trueOrFalse;
        return $this;
    }
    
    /**
     *
     * @see iCanAutoloadData::getAutorefreshData()
     */
    public function hasAutorefreshData() : bool
    {
        return $this->autorefresh;
    }
}