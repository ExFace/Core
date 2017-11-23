<?php
namespace exface\Core\Exceptions\UiPage;

use exface\Core\Exceptions\RuntimeException;

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
