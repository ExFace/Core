<?php
namespace exface\Core\Exceptions\Contexts;

/**
 * Exception thrown if a context fails to load data from the respective scope.
 *
 * @author Andrej Kabachnik
 *        
 */
class ContextLoadError extends ContextRuntimeError
{

    public function getDefaultAlias()
    {
        return '6T5E400';
    }
}