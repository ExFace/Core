<?php

namespace exface\Core\CommonLogic\Permalink;

use exface\Core\CommonLogic\DataSheets\DataCheck;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Permalinks\PermalinkInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use Flow\JSONPath\JSONPath;

/**
 * Base class for all Permalink prototypes with constructor and basic setters.
 */
abstract class AbstractPermalink implements PermalinkInterface
{
    use ImportUxonObjectTrait;

    private ?UxonObject $uxon = null;
    private ?WorkbenchInterface $workbench;
    private ?string $name = null;
    private ?string $aliasWithNamespace = null;
    private ?string $exampleParams = null;
    private array $destinationProfile = [];

    /**
     * @param WorkbenchInterface $workbench
     * @param string             $aliasWithNamespace
     * @param UxonObject|null    $configUxon
     */
    public function __construct(WorkbenchInterface $workbench, string $aliasWithNamespace, ?UxonObject $configUxon = null)
    {
        $this->workbench = $workbench;
        $this->aliasWithNamespace = $aliasWithNamespace;
        $this->uxon = $configUxon;
        
        if ($configUxon !== null) {
            $this->importUxonObject($configUxon);
        }
    }

    /**
     * @inheritdoc 
     * @see PermalinkInterface::withUrl()
     */
    public function withUrl(string $innerUrl) : PermalinkInterface
    {
        $clone = clone $this;
        return $clone->parse($innerUrl);
    }

    /**
     * Parses the inner URL provided as arguments, possibly changing the state of this instance.
     * 
     * @param string $innerUrl
     * @return PermalinkInterface
     */
    protected abstract function parse(string $innerUrl) : PermalinkInterface;

    /**
     * @param string $aliasWithNamespace
     * @return PermalinkInterface
     */
    public function setAlias(string $aliasWithNamespace) : PermalinkInterface
    {
        $this->aliasWithNamespace = $aliasWithNamespace;
        return $this;
    }

    /**
     * @return string
     */
    public function getAliasWithNamespace() : string
    {
        return $this->aliasWithNamespace;
    }

    /**
     * @inheritdoc 
     * @see WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench() : WorkbenchInterface
    {
        return $this->workbench;
    }

    /**
     * @inheritDoc
     */
    public function exportUxonObject() : UxonObject
    {
        return $this->uxon ?? new UxonObject();
    }

    /**
     * @uxon-property example_params
     * @uxon-type string
     * 
     * @param string $params
     * @return $this
     */
    public function setExampleParams(string $params) : AbstractPermalink
    {
        $this->exampleParams = $params;
        return $this;
    }

    /**
     * @inheritdoc 
     * @see PermalinkInterface::getExampleParams()
     */
    public function getExampleParams() : ?string
    {
        return $this->exampleParams;
    }

    /**
     * @uxon-property destination_profile
     * @uxon-type \exface\Core\CommonLogic\Model\Condition[]
     * @uxon-template [{"expression": "", "comparator": "", "value": ""}]
     *
     * @param UxonObject $profile
     * @return PermalinkInterface
     */
    public function setDestinationProfile(UxonObject $profile): PermalinkInterface
    {
        $this->destinationProfile = $profile->toArray();
        return $this;
    }

    /**
     * @return Condition[]
     */
    public function getDestinationProfile(): array
    {
        return $this->destinationProfile;
    }
    
    public function destinationMatchesProfile(UxonObject $destinationUxon) : bool
    {
        $profile = $this->getDestinationProfile();
        if(empty($profile)) {
            return true;
        }
        
        $json = new JSONPath($destinationUxon->toArray());
        foreach ($profile as $condition) {
            if(!$json->find($condition['expression'])) {
                return false;
            }
            
        }
        
        return true;
    }

    /**
     * @inheritdoc 
     * @see PermalinkInterface::buildAbsoluteRedirectUrl()
     */
    public function buildAbsoluteRedirectUrl() : string
    {
        return $this->getWorkbench()->getUrl() . $this->buildRelativeRedirectUrl();
    }
}