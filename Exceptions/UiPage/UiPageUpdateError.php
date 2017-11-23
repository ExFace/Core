<?php
namespace exface\Core\Exceptions\UiPage;

use exface\Core\Exceptions\RuntimeException;

/**
 * Error thrown when a problem updating a UiPage occurs.
 *
 * @author SFL
 *
 */
class UiPageUpdateError extends RuntimeException
{

    public function getDefaultAlias()
    {
        return '6XX8HFA';
    }
}
