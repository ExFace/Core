<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Interfaces;

/**
 * Facade elements implementing this interface can check, whether a candidate value would be
 * valid for their widget without actually changing the widget's current value.
 * 
 * This is used, for example, by the `SpinnerFilter` to disable its +/- buttons if adding or
 * subtracting the step would result in an invalid value (e.g. a value not found by an
 * `InputComboTable`).
 * 
 * @author Andrej Kabachnik
 *
 */
interface JsValueValidityCheckerInterface
{
    /**
     * Returns inline JS code, that asynchronously checks, whether `$jsCandidateValue` would be
     * a valid value for this element and calls `$jsCallback` with a boolean once the check is
     * done - without changing the element's own current value.
     * 
     * @param string $jsCandidateValue
     * @param string $jsCallback
     * @return string
     */
    public function buildJsCheckValueValid(string $jsCandidateValue, string $jsCallback) : string;
}
