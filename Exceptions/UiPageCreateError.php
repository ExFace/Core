<?php
namespace exface\Core\Exceptions;

/**
 * Error thrown when a problem creating a UiPage occurs.
 * 
 * @author SFL
 *
 */
class UiPageCreateError extends RuntimeException
{

    public function getDefaultAlias()
    {
        return '6XX8HAD';
    }
}
