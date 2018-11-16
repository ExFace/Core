<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Factories\WidgetFactory;

/**
 * The preloader allows to configure a widget to keep certain data on the client (preload it).
 * 
 * If supported by the template, preloading can be used to improve performnce (e.g. if only a
 * small part of the data is required for daily work) or event to create an offline data storage
 * for a progressive web app.
 * 
 * If a widget supports data preloading, it can be easily enabled by setting it's `preload_data`
 * property to `true` (to preload all the data) or a data widget definition to specify, which
 * data needs to be preloaded.
 * 
 * Example 1 (preload all data of a table):
 * 
 * ```
 * {
 *  "widget_type": "DataTable",
 *  "object_alias": "exface.Core.OBJECT",
 *  "preload_data": true
 * }
 * 
 * ```
 * 
 * Example 2 (preload only meta object of the core app and sync them every 30 seconds)
 * 
 * ```
 * {
 *  "widget_type": "DataTable",
 *  "object_alias": "exface.Core.OBJECT",
 *  "preload_data": {
 *      "data": {
 *          "filters": [
 *              {"attribute_alias": "APP__ALIAS", "value": "exface.Core"}
 *          ]
 *      },
 *      "sync_interval": 30
 *  }
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class DataPreloader implements iCanBeConvertedToUxon
{    
    use ImportUxonObjectTrait;
    
    private $widget;
    
    private $prelaodData = null;
    
    private $preloadAll = false;
    
    private $syncInterval = null;
    
    public function __construct(WidgetInterface $widget)
    {
        $this->widget = $widget;
    }
    
    public function getWidget() : WidgetInterface
    {
        return $this->widget;
    }
    
    public function getWorkbench()
    {
        return $this->widget->getWorkbench();
    }
    
    /**
     * Set to TRUE to preload all data.
     * 
     * @uxon-property preload_all
     * @uxon-type boolean
     * 
     * @return DataPreloader
     */
    public function setPreloadAll() : DataPreloader
    {
        $this->preloadAll = true;
        return $this;
    }
    
    /**
     * Disables preloading completely (e.g. to temporarily disable a configured preloader)
     * 
     * @return DataPreloader
     */
    public function disable() : DataPreloader
    {
        $this->preloadAll = false;
        return $this;
    }
    
    /**
     * Returns TRUE if the preloader is configured to preload some data.
     * 
     * @return bool
     */
    public function isEnabled() : bool
    {
        return $this->preloadAll === true || $this->prelaodData !== null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return new UxonObject([
            
        ]);
    }
    
    public function prepareDataSheetToPreload(DataSheetInterface $dataSheet) : DataSheetInterface
    {
        $ds = $this->getWidget()->prepareDataSheetToRead($dataSheet);
        $ds->setRowsOffset(0);
        $ds->setRowsLimit(null);
        return $ds;
    }
    
    /**
     * Specifies a subset of data to be preloaded by defining a data widget or parts of it (e.g. filters).
     * 
     * If you do not need 
     * 
     * @uxon-property data
     * @uxon-type \exface\Core\Widgets\Data
     * 
     * @param UxonObject $uxon
     * @return DataPreloader
     */
    public function setData(UxonObject $uxon) : DataPreloader
    {
        return $this->setPreloadData(WidgetFactory::createFromUxon($this->getWidget()->getPage(), $uxon, $this->getWidget(), 'Data'));
    }
    
    /**
     * 
     * @param Data $widget
     * @return DataPreloader
     */
    public function setPreloadData(Data $widget) : DataPreloader
    {
        $this->prelaodData = $widget;
        return $this;
    }
    
    /**
     *
     * @return int
     */
    public function getSyncInterval() : int
    {
        return $this->syncInterval;
    }
    
    /**
     * Sets the interval to sync preloaded data from the server (in seconds)
     * 
     * @uxon-property sync_interval
     * @uxon-type integer
     * 
     * @param int $value
     * @return DataPreloader
     */
    public function setSyncInterval(int $value) : DataPreloader
    {
        $this->syncInterval = $value;
        return $this;
    }
}