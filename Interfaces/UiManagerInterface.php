<?php
namespace exface\Core\Interfaces;

use exface\Core\Interfaces\Templates\TemplateInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;

interface UiManagerInterface extends ExfaceClassInterface
{
    /**
     * Returns the UI page with the given $page_alias.
     * If the $page_alias is ommitted or ='', the default (initially empty) page is returned.
     *
     * @param UiPageSelectorInterface|string $selectorOrString
     * @return UiPageInterface
     */
    public function getPage($selectorOrString);
}

?>