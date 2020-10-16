<?php
namespace exface\Core\Interfaces\Facades;

use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;

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
}
