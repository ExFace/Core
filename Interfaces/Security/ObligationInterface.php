<?php
namespace exface\Core\Interfaces\Security;

/**
 * Obligations describe actions, restrictions or any other logic that must be applied together with the policy
 * 
 * Obligations can be very different. They MUST be fulfilled by the authorization point. Each authorization point 
 * MAY or MAY NOT support certain types of obligations. However, in the end, every obligation MUST must be fulfilled,
 * otherwise the authorization point MUST throw an exception.
 * 
 * Examples of obligations
 * - Log the authorization request with a ceratin level: e.g. to forcibly log critical authorization
 * - Add mandatory filters to data - only makes sense for authorization points, that deal with data
 * 
 * @author Andrej Kabachnik
 *
 */
interface ObligationInterface
{
    public function setFulfilled(bool $trueOrFalse) : ObligationInterface;
    
    public function isFulfilled() : bool;
    
    public function getExplanation() : string;
}