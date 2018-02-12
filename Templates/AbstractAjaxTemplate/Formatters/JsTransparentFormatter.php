<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Formatters;

/**
 * The transparent formatter does not do any formatting, but just returns it's input as is.
 * 
 * This formatter can be used as fallback in case no specific formatter was set.
 * 
 * @author Andrej Kabachnik
 *
 */
class JsTransparentFormatter extends AbstractJsDataTypeFormatter
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractAjaxTemplate\Interfaces\JsDataTypeFormatterInterface::buildJsFormatter()
     */
    public function buildJsFormatter($jsInput)
    {
        return $jsInput;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractAjaxTemplate\Interfaces\JsDataTypeFormatterInterface::buildJsFormatParser()
     */
    public function buildJsFormatParser($jsInput)
    {
        return $jsInput;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractAjaxTemplate\Interfaces\JsDataTypeFormatterInterface::buildHtmlHeadIncludes()
     */
    public function buildHtmlHeadIncludes()
    {
        return [];
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractAjaxTemplate\Interfaces\JsDataTypeFormatterInterface::buildHtmlBodyIncludes()
     */
    public function buildHtmlBodyIncludes()
    {
        return [];
    }    
}
