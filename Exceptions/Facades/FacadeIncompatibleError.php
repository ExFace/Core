<?php
namespace exface\Core\Exceptions\Facades;

use exface\Core\Exceptions\LogicException;

/**
 * Exception thrown if the used facade is incompatible with the request/command
 * (e.g. an HTTP facade is used for a console command).
 *
 * @author Andrej Kabachnik
 *        
 */
class FacadeIncompatibleError extends LogicException
{
}