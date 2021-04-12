<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
interface iCanAutoloadData extends WidgetInterface
{
    /**
     * 
     * @return bool
     */
    public function hasAutoloadData() : bool;
    
    /**
     * Set to TRUE/FALSE to force/prevent initial loading of data or TRUE (default) to enable it.
     *
     * @param boolean $autoloadData
     * @return iCanAutoloadData
     */
    public function setAutoloadData(bool $trueOrFalse) : iCanAutoloadData;
    
    /**
     * Returns a text which can be displayed if initial loading is prevented.
     *
     * @return string
     */
    public function getAutoloadDisabledHint() : string;
    
    /**
     * Overrides the text shown if autoload_data is set to FALSE or required filters are missing.
     *
     * @param string $text
     * @return iCanAutoloadData 
     */
    public function setAutoloadDisabledHint(string $text) : iCanAutoloadData;
    
    /**
     * Set to TRUE/FALSE to force/disable automatic refreshes of this widget after actions are performed.
     *
     * @param bool $trueOrFalse
     * @return iShowData
     */
    public function setAutorefreshData(bool $trueOrFalse) : iCanAutoloadData;
    
    /**
     *
     * @return bool
     */
    public function hasAutorefreshData() : bool;
}