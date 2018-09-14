<?php
namespace exface\Core\Exceptions\Templates;

use exface\Core\Exceptions\LogicException;

/**
 * Exception thrown if the used template is incompatible with the request/command
 * (e.g. an HTTP template is used for a console command).
 *
 * @author Andrej Kabachnik
 *        
 */
class TemplateIncompatibleError extends LogicException
{
}