<?php

namespace exface\Core\Permalinks;

use exface\Core\CommonLogic\Permalink\AbstractPermalink;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\DataTypes\UUIDDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\UxonParserError;
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
 * - `api/files/otl/my.App.OBJECT_ALIAS/7a2aadee-60cc-4029-845d-125bf14623ad`
 * - `api/permalink/exface.Core.OTL/7a2aadee-60cc-4029-845d-125bf14623ad`
 */
class OneTimeLink extends AbstractPermalink
{    
    const TEST_SLUG = '7a2aadee-60cc-4029-845d-125bf14623ad';
    
    private ?string $slug = null;
    
    private ?string $fileUrl = null;
    
    /**
     * @inheritdoc 
     * @see AbstractPermalink::parse()
     */
    protected function parse(string $innerUrl) : PermalinkInterface
    {
        // TODO Load the real URL from the given UUID
        $this->fileUrl = $innerUrl;
        return $this;
    }

    /**
     * @inheritdoc 
     * @see PermalinkInterface::buildRelativeRedirectUrl()
     */
    public function buildRelativeRedirectUrl() : string
    {
        // TODO return something like api/files/...
        $path = parse_url($this->fileUrl, PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', $path), fn($s) => $s !== ''));
        $filesIdx = array_search('files', $segments, true);
        $route = $segments[$filesIdx + 1]; // e.g. "download" or "thumb"
        if ($route === 'download') {
            $slug = $this->createOTL();
            $this->saveSlug($slug);
            return $slug;
        }
        
        return $this->getRedirectLink($this->fileUrl);
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
    
    private function getPermalinkUid() : string
    {
        $permalink = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.PERMALINK');
        $permalink->getFilters()->addConditionFromString('PROTOTYPE_FILE', 'exface/Core/Permalinks/OneTimeLink.php', ComparatorDataType::EQUALS);
        $permalink->getColumns()->addMultiple(['UID', 'ALIAS', 'APP__ALIAS']);
        $permalink->dataRead();
        if ($permalink->countRows() === 0) {
            throw new RuntimeException('One Time Link Permalink cannot be found (prototype "exface/Core/Permalinks/OneTimeLink.php"');
        }
            
        $row = $permalink->getRow();
        return $row['UID'];
    }
    
    private function saveSlug(string $slug) :void
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.PERMALINK_SLUG');
        $ds->addRow([
            'PERMALINK' => $this->getPermalinkUid(),
            'SLUG' => $slug,
            'DATA_UXON' => UxonObject::fromArray(array('file_url'=> $this->fileUrl))->toJson()
        ]);
        $ds->dataCreate(false);
    }

    private function getRedirectLink(?string $slug) :string
    {
        if ($slug === self::TEST_SLUG) {
            return "/exface.core.permalinks.html";
        }
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.PERMALINK_SLUG');
        $ds->getColumns()->addMultiple(['DATA_UXON']);
        $ds->getFilters()->addConditionFromString('PERMALINK', $this->getPermalinkUid(), ComparatorDataType::EQUALS);
        $ds->getFilters()->addConditionFromString('SLUG', $slug, ComparatorDataType::EQUALS);
        $ds->dataRead();

        switch ($ds->countRows()) {
            case 0:
                throw new RuntimeException('Permalink slug "' . $slug . '" not found!');
            case 1:
                // Everything is fine - continue
                break;
            default:
                throw new RuntimeException('Permalink slug "' . $slug . '" not unique - found ' . $ds->countRows() . ' of them!');
        }

        $uxon = UxonObject::fromJson($ds->getRow()['DATA_UXON']);
        $url = $uxon->getProperty('file_url');
        if (empty($url) || ! is_string($url)) {
            throw new UxonParserError($uxon, 'Invalid data_uxon: missing "file_url" for slug "' . $slug . '"');
        }
        $ds->dataDelete();
        return $url;
    }
    
    private function createOTL() :string
    {
        return UUIDDataType::generateUuidV4('');
    }
}