<?php

namespace exface\Core\Interfaces;

use exface\Core\CommonLogic\AppInstallers\AppInstallerContainer;

/**
 * An empty marker interface. Systems that recognize this interface will not dispatch events
 * when processing objects that implement it.
 * 
 * NOTE: The exact behavior depends on the system and is not guaranteed.
 *
 * @see AppInstallerContainer
 */
interface IAmSilentInterface
{

}