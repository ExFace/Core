<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\DataTypes\AutoloadStrategyDataType;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Widgets\iCanAutoloadData;

/**
 * This trait contains common methods to implement the iCanAutoloadData interface.
 * 
 * @author Andrej Kabachnik
 */
trait iCanAutoloadDataTrait {
    
    private string $autoload_data = AutoloadStrategyDataType::ALWAYS;
    
    private ?string $autoload_disabled_hint = null;
    
    private bool $autorefresh = true;
    
    private ?int $autorefresh_seconds = null;
    
    /**
     * 
     * @see iCanAutoloadData::hasAutoloadData()
     */
    public function hasAutoloadData() : bool
    {
        return $this->autoload_data !== AutoloadStrategyDataType::NEVER;
    }

    /**
     *
     * @see iCanAutoloadData::getAutoloadDataStrategy()
     */
    public function getAutoloadDataStrategy() : string
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
     * @uxon-type [never,always,if_visible]
     *
     * @see iCanAutoloadData::setAutoloadData()
     */
    public function setAutoloadData(string $autoloadData) : iCanAutoloadData
    {
        // Backwards compatibility to booleans
        switch (true) {
            case $autoloadData === '1': $autoloadData = AutoloadStrategyDataType::ALWAYS; break;
            case $autoloadData === '0': $autoloadData = AutoloadStrategyDataType::NEVER; break;
            case ! AutoloadStrategyDataType::isValidStaticValue($autoloadData):
                throw new WidgetConfigurationError($this, 'Invalid value "' . $autoloadData . '" for property `autoload_data` of widget ' . $this->getWidgetType());
        }
        
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
    
    /**
     * Set the intervall for the data to be automatically refreshed in.
     *
     * @uxon-property autorefresh_intervall
     * @uxon-type int
     * @uxon-default 60
     * 
     * @param int $seconds
     * @return iCanAutoloadData
     */
    public function setAutorefreshIntervall(int $seconds) : iCanAutoloadData
    {
        $this->autorefresh_seconds = $seconds;
        return $this;
    }
    
    /**
     * Returns the autorefresh intervall in seconds.
     * 
     * @return int|NULL
     */
    public function getAutorefreshIntervall() : ?int
    {
        return $this->autorefresh_seconds;
    }
    
   
    /**
     * 
     * @return bool
     */
    public function hasAutorefreshIntervall() : bool
    {
        return $this->autorefresh_seconds !== null;
    }
}