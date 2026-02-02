<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Interfaces;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;

/**
 * This interface defines the structure of reusable data type formatters for
 * javascript facades.
 * 
 * @author Andrej Kabachnik
 *
 */
interface JsDataTypeFormatterInterface 
{
    
    /**
     * Returns the data type used in this formatter.
     * 
     * @return DataTypeInterface
     */
    public function getDataType();
    
    /**
     * Returns an array of HTML include tags (<script>, <link>, etc.) to be placed in the <head> section.
     * 
     * Each element of the array must hold exactly one tag including the action opening an closing HTML tags.
     * 
     * @return string[]
     */
    public function buildHtmlHeadIncludes(FacadeInterface $facade) : array;
    
    /**
     * Returns an array of HTML include tags (<script>, <link>, etc.) to be placed in the <body> section.
     *
     * Each element of the array must hold exactly one tag including the action opening an closing HTML tags.
     *
     * @return string[]
     */
    public function buildHtmlBodyIncludes(FacadeInterface $facade) : array;
    
    /**
     * Returns inline embeddable javascript code to format the input containing a normalized value.
     * 
     * For example, in a date formatter this method would format a normalized date like 2011-12-31 
     * into a locale specific formatted value like 31.12.2011 for the german locale.
     * 
     * The input must be valid javascript code: e.g. a variable, a function call or a quoted string.
     * 
     * @param string $jsInput
     * @return string
     */
    public function buildJsFormatter($jsInput);
    
    /**
     * Returns inline embeddable javascript code to parse the formatted input input a normalized value.
     * 
     * This is the reverse function of the formatter: e.g. in a date formatter it would parse a locale 
     * specific date like 31.12.2011 into an internal normalized date like 2011-12-31.
     * 
     * The input must be valid javascript code: e.g. a variable, a function call or a quoted string.
     * 
     * @param string $jsInput
     * @return string
     */
    public function buildJsFormatParser($jsInput);

    public function buildJsValidator(string $jsValue) : string;

    /**
     * Builds an inline JS-Snippet that returns a string with details regarding any validation issues detected
     * for a given input. The snippet returns an empty string for valid inputs.
     * 
     * @param string $jsValue
     * @return string
     */
    public function buildJsGetValidatorIssues(string $jsValue) : string;

    /**
     * Returns an inline JS snippet that contains a fallback value for empty values (such as `null`).
     *
     * @param string $jsFallback
     * If the underlying `DataType` does not have an empty text, this JS snippet will be returned instead.
     * @param bool   $encode
     * Set to FALSE if you DON'T want the return value to be encoded (i.e. escaped and enquoted).
     * @return string|null
     * @see DataTypeInterface::getEmptyText()
     */
    public function getJsEmptyText(string $jsFallback, bool $encode = true) : ?string;
}