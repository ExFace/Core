<?php
namespace exface\Core\Exceptions\UiPage;

use exface\Core\Exceptions\RuntimeException;

/**
 * Exception thrown if the UiPage is not part of any app.
 * 
 * @author SFL
 *
 */
class UiPageNotPartOfAppError extends RuntimeException
{

    public function getDefaultAlias()
    {
        return '6XHA8KR';
    }
}
