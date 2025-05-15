<?php
namespace exface\Core\CommonLogic\Traits;

use exface\Core\CommonLogic\Security\Authorization\DataAuthorizationPoint;
use Throwable;

/**
 * This trait allows to quickly add logic to bypass data authorization UXON prototype classes like behaviors.
 * 
 * The user cann request bypassing authorization by setting `bypass_data_authorization_point` to TRUE.
 * 
 * The code, that should actually be run without data authorization should be passed to `bypassDataAuthorization()`
 * as a callable. This will disable the data authoriaztion point an re-enable it after the code is done or an
 * exception is caught.
 * 
 */
trait ICanBypassDataAuthorizationTrait {
    
    private $bypassDataAuthorizationPoint = null;

    protected function bypassDataAuthorization(callable $callback)
    {
        $dataAP = $this->getWorkbench()->getSecurity()->getAuthorizationPoint(DataAuthorizationPoint::class);
        $wasDisabled = $dataAP->isDisabled();
        $dataAP->setDisabled(true);
        try {
            return $callback();
        } catch (Throwable $e) {
            throw $e;
        } finally {
            $dataAP->setDisabled($wasDisabled);
        }
    }

    /**
     * Returns TRUE or FALSE if `bypass_data_authorization_point` is explicitly set and NULL otherwise
     * 
     * @return bool|null
     */
    protected function willBypassDataAuthorizationPoint() : ?bool
    {
        return $this->bypassDataAuthorizationPoint;
    }

    /**
     * Set to TRUE to disable data authorization for the called action or to FALSE to force data authorization explicitly
     * 
     * In general, the workbench attempts to figure out automatically, in which case it needs to
     * bypass data authorization. However, there are cases, when this results in a different
     * behavior than expected by a human - that's when you can override it using this property.
     * 
     * @uxon-property bypass_data_authorization_point
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return static
     */
    protected function setBypassDataAuthorizationPoint(bool $value) : static
    {
        $this->bypassDataAuthorizationPoint = $value;
        return $this;
    }
}
