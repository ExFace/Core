<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for authorization point selectors.
 * 
 * An authorization point can be identified by 
 * - it's qualified class name: e.g. `\exface\Core\CommonLogic\Security\Authorization\UiPageAuthorizationPoint`
 * - the path to it's PHP file relative to the vendor folder
 * 
 * @author Andrej Kabachnik
 *
 */
interface AuthorizationPointSelectorInterface extends PrototypeSelectorInterface
{}