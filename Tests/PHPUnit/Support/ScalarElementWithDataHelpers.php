<?php
namespace exface\Core\Tests\PHPUnit\Support;

use exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JsDataValueTrait;

/**
 * Provides shared data helpers without overriding the default scalar getter.
 */
abstract class ScalarElementWithDataHelpers extends AbstractJqueryElement
{
    use JsDataValueTrait;
}
