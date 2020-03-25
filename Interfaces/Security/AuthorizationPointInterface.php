<?php
namespace exface\Core\Interfaces\Security;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\PolicyEffectDataType;
use exface\Core\DataTypes\PolicyCombiningAlgorithmDataType;
use exface\Core\Interfaces\Exceptions\AuthorizationExceptionInterface;

/**
 * The authorization point represents a access permission check in an app's security logic.
 * 
 * For example, the `Core` app checks if a user has access to pages, to actions, etc. 
 * Accordingly, there is the `UiPageAuthorizationPoint`, the `ActionAuthorizationPoint`,
 * and so on. 
 * 
 * Every such access permission check basically instantiates it's authorization
 * point (AP), loads it's configuration from the metamodel and calls it's `authorize()`
 * method with arguments appropriate for the specific AP - typically the subject (user),
 * the resource being accessed and/or the action being performed. The AP performs it's 
 * internal logic and either grants permission by returning the resource or throwing
 * an authorization exception. 
 * 
 * Obviously, the arguments and the return value of the `authorize()` method depend
 * on the logic and capabilities of the AP and the code surrunding it. Apps may have
 * very different access restriction models - often even dictated by the security
 * logic of a remote system or data source. Since the workbench is actully in charge 
 * of interacting with the user, it must adhere to all that logic while still
 * providing understandable feedback to the user and handable configuration options.
 * 
 * Having a central interface to the authorization logic (consisting of APs and their 
 * metamodel) allows to present different approaches to access permission in a coherent 
 * way, so users and administrators can stay on top of things dispite all the different 
 * (possibly complex) restriction configurations.
 * 
 * @author Andrej Kabachnik
 *
 */
interface AuthorizationPointInterface extends WorkbenchDependantInterface, AliasInterface
{    
    /**
     * Evaluates the logic of the authorization point triggering the OnAuthorizedEvent
     * or throwing an authorization exception depending on the result.
     * 
     * Every authorization point (AP) has it's own internal logic and event it's own 
     * input and output parameters. The workbech is notified about the access decision
     * via event or exception.
     * 
     * Generally the method should accept all input parameters needed and return the
     * (possibly modified) object or resource, access to whic was being authorized.
     * Returning the resource makes it possible for authorization points to modify it:
     * e.g. disable certain buttons of a widget (if it's a widget), add additional
     * configuration to an action, etc.
     * 
     * In any case, this method MUST trigger the events listed below to ensure other
     * kinds of authorization logic can hook in correctly.
     * 
     * @triggers \exface\Core\Events\Security\OnAuthorizedEvent
     * 
     * @throws AuthorizationExceptionInterface
     * 
     * @return mixed
     */
    public function authorize();
    
    /**
     * Returns the app, that is responsible for the authorization point.
     * 
     * @return AppInterface
     */
    public function getApp() : AppInterface;
    
    /**
     * Allows to add policies to the authorization point.
     * 
     * @param array $targets
     * @param UxonObject $condition
     * @param PolicyEffectDataType $effect
     * @param string $name
     * 
     * @return AuthorizationPointInterface
     */
    public function addPolicy(array $targets, PolicyEffectDataType $effect, string $name = '', UxonObject $condition = null) : AuthorizationPointInterface;
    
    /**
     * 
     * @param bool $trueOrFalse
     * 
     * @return AuthorizationPointInterface
     */
    public function setActive(bool $trueOrFalse) : AuthorizationPointInterface;
    
    /**
     * 
     * @param PolicyEffectDataType $effect
     * @return AuthorizationPointInterface
     */
    public function setDefaultPolicyEffect(PolicyEffectDataType $effect) : AuthorizationPointInterface;
    
    /**
     * 
     * @return PolicyEffectDataType
     */
    public function getDefaultPolicyEffect() : PolicyEffectDataType;
    
    /**
     * 
     * @return PolicyCombiningAlgorithmDataType
     */
    public function getPolicyCombiningAlgorithm() : PolicyCombiningAlgorithmDataType;
    
    /**
     * 
     * @param PolicyCombiningAlgorithmDataType $algorithm
     * @return AuthorizationPointInterface
     */
    public function setPolicyCombiningAlgorithm(PolicyCombiningAlgorithmDataType $algorithm) : AuthorizationPointInterface;
    
    /**
     * 
     * @param string $name
     * @return AuthorizationPointInterface
     */
    public function setName(string $name) : AuthorizationPointInterface;

    /**
     * 
     * @return string
     */
    public function getName() : string;
    
    /**
     *
     * @return string
     */
    public function getUid() : string;
    
    /**
     *
     * @param string $value
     * @return AuthorizationPointInterface
     */
    public function setUid(string $value) : AuthorizationPointInterface;
}