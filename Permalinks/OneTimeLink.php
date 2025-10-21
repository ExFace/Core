<?php

namespace exface\Core\Permalinks;

use exface\Core\CommonLogic\Permalink\AbstractPermalink;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\Facades\HtmlPageFacadeInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Permalinks\PermalinkInterface;
use exface\Core\Interfaces\WidgetInterface;
use http\Exception\BadUrlException;

/**
 * 
 * 
 * **Link Syntax:** 
 * - `api/files/otl/my.App.OBJECT_ALIAS/0x5468789`
 * - `api/permalink/exface.core.otl/my.App.OBJECT_ALIAS/0x5468789`
 */
class OneTimeLink extends AbstractPermalink
{
    

    /**
     * @inheritdoc 
     * @see AbstractPermalink::parse()
     */
    protected function parse(string $innerUrl) : PermalinkInterface
    {
        // Load the real URL from the given UUID
        return $this;
    }

    /**
     * @inheritdoc 
     * @see PermalinkInterface::buildRelativeRedirectUrl()
     */
    public function buildRelativeRedirectUrl() : string
    {
        // TODO return something like api/files/...
    }

    /**
     * Returns original pathURL (`config_alias/target_uid`) without the facade routing, 
     * for example `exface.Core.show_object/1260-TB`
     * 
     * @return string
     */
    public function __toString(): string
    {
        // TODO this is optional. This method is used to hande permalinks via PermalinkFacade, but for now we
        // concentrate on the HttpFileServerFacade only.
        return $this->getAliasWithNamespace() . '/' . $this->uid;
    }
}