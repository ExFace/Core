<?php

namespace exface\Core\Exceptions\Actions;

/**
 * Exception thrown if something goes wrong when processing the output of the action: e.g.
 * if the output cannot be converted
 * to the required format, etc.
 *
 * @author Andrej Kabachnik
 *        
 */
class ActionOutputError extends ActionInputError
{
}
