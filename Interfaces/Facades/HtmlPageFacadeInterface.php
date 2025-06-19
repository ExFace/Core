<?php
namespace exface\Core\Interfaces\Facades;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Interfaces\WidgetInterface;

/**
 * Interface for facades, that render UI pages as HTML
 * 
 * @author Andrej Kabachnik
 *
 */
interface HtmlPageFacadeInterface extends HttpFacadeInterface
{    
    /**
     * 
     * @param UiPageInterface|UiPageSelectorInterface|string $pageOrSelectorOrString
     * @param string $url_params
     * @return string
     */
    public function buildUrlToPage($pageOrSelectorOrString, string $url_params = '') : string;

    /**
     * Returns a relative URL, that will show the given widget
     *
     * @param WidgetInterface $widget
     * @param DataSheetInterface|null $prefillData
     * @return string
     */
    public function buildUrlToWidget(WidgetInterface $widget, DataSheetInterface $prefillData = null) : string;
}
