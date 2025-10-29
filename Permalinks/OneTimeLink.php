<?php

namespace exface\Core\Permalinks;

use exface\Core\CommonLogic\Permalink\AbstractPermalink;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\DataTypes\UUIDDataType;
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
    private ?string $fileUrlNormalized = null;
    
    private ?string $slug = null;
    
    private ?string $permalinkAlias = null;
    
    private ?string $fileUrl = null;
    
    /**
     * @inheritdoc 
     * @see AbstractPermalink::parse()
     */
    protected function parse(string $innerUrl) : PermalinkInterface
    {
        // TODO Load the real URL from the given UUID
        $this->fileUrl = $innerUrl;
        /**
                // Decide if $innerUrl is a FILE URL (creation) or a SLUG (resolve)
                $in = urldecode($innerUrl);
        
                // If it looks like /api/files/..., treat as creation:
                if (preg_match('#(^https?://[^/]+)?/?api/files/#i', $in)) {
                    $this->fileUrlNormalized = $this->normalizeFileUrl($in);
                    return $this;
                }
        
                // Otherwise treat as slug (resolve):
                $this->slug = trim($in, '/');
                if ($this->slug === '') {
                    throw new \RuntimeException('Invalid OneTimeLink input: empty slug.');
                }
         * 
         * */
        return $this;
    }

    /**
     * @inheritdoc 
     * @see PermalinkInterface::buildRelativeRedirectUrl()
     */
    public function buildRelativeRedirectUrl() : string
    {
        // TODO return something like api/files/...
        /**
        if ($this->slug !== null) {
            return $this->getRedirectLink($this->slug);
        }
        
        if ($this->fileUrlNormalized === null) {
            throw new \LogicException('No file URL parsed. Ensure withUrl($realFileUrl) was called.');
        }
        $slug = $this->getSlug();
        if ($slug === null) {
            $slug = $this->makeDeterministicSlug($this->fileUrlNormalized);
            $this->saveSlug($slug);
        }
        return \exface\Core\Factories\PermalinkFactory::buildRelativePermalinkUrl(
            $this->permalinkAlias,
            rawurlencode($slug)
        );
         * 
         */
        $parts = parse_url($this->fileUrl);
        $path  = $parts['path'] ?? '';
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
        $row = $permalink->getRow();
        if ($this->permalinkAlias === null) {
            $this->permalinkAlias = $row['APP__ALIAS'] . '.' . $row['ALIAS'];
        }
        return $row['UID'];
    }
    
    private function saveSlug(string $slug) :void
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.PERMALINK_SLUG');
        $ds->addRow([
            'PERMALINK' => $this->getPermalinkUid(),
            'SLUG' => $slug,
            'DATA_UXON' => UxonObject::fromArray(array('file_url'=> $this->fileUrlNormalized ?? $this->fileUrl))->toJson()
        ]);
        $ds->dataCreate();
    }


    private function getSlug(): ?string
    {
        $slug  = $this->makeDeterministicSlug($this->fileUrlNormalized);

        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.PERMALINK_SLUG');
        $ds->getFilters()->addConditionFromString('PERMALINK', $this->getPermalinkUid(), ComparatorDataType::EQUALS);
        $ds->getFilters()->addConditionFromString('SLUG', $slug, ComparatorDataType::EQUALS);
        $ds->getColumns()->addMultiple(['SLUG']);
        $ds->dataRead();

        return $ds->countRows() > 0 ? $ds->getRows()[0]['SLUG'] : null;
    }

    private function getRedirectLink(?string $slug) :string
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.PERMALINK_SLUG');
        $ds->getColumns()->addMultiple(['DATA_UXON']);
        $ds->getFilters()->addConditionFromString('PERMALINK', $this->getPermalinkUid(), ComparatorDataType::EQUALS);
        $ds->getFilters()->addConditionFromString('SLUG', $slug, ComparatorDataType::EQUALS);
        $ds->dataRead();

        if ($ds->countRows() !== 1) {
            throw new \RuntimeException('Permalink slug not found or not unique: ' . $this->slug);
        }

        $uxon = UxonObject::fromJson($ds->getRow()['DATA_UXON'])->toArray();
        if (empty($uxon['file_url']) || !is_string($uxon['file_url'])) {
            throw new \RuntimeException('Invalid data_uxon: missing "file_url" for slug ' . $this->slug);
        }
        $ds->dataDelete();
        return $uxon['file_url'];
    }
    
    private function createOTL() :string
    {
        return UUIDDataType::generateUuidV4('');

    }
    /**
     * Very light normalization:
     * - drop scheme+host
     * - ensure single leading slash removed
     * - keep path and query as-is (so different resize stays different)
     */
    private function normalizeFileUrl(string $url): string
    {
        $p = parse_url($url);
        $path = ltrim($p['path'] ?? '', '/');
        $query = isset($p['query']) ? ('?' . $p['query']) : '';
        return $path . $query;
    }
}