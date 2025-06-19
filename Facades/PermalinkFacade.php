<?php
namespace exface\Core\Facades;

use exface\Core\CommonLogic\Selectors\PermalinkSelector;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\PhpFilePathDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\Configuration\ConfigOptionNotFoundError;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Permalinks\PermalinkInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Redirects the browser to a dynamic URL from a long-term link.
 * 
 * ## Link Syntax:
 * 
 * **General:**
 * - `api/permalink/<config_alias>/[parameters]`
 *     
 * **Opening the object editor:**
 * - `api/permalink/exface.Core.show_object/[target_uid]` - open the object editor
 * 
 * **Running a DataFlow with parameters:**
 * - `api/permalink/my.app.run_data_flow/<uid_of_flow>/<param1>/<param2>` - run a DataFlow with parameters
 * 
 * @author Andrej Kabachnik
 *
 */
class PermalinkFacade extends AbstractHttpFacade
{
    public const STATUS_CODE = 301; // Permanent redirect.
    public const URL_ROUTE = 'api/permalink';
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::createResponse()
     */
    protected function createResponse(ServerRequestInterface $request) : ResponseInterface
    {
        // Parse URL.
        $requestUri = $request->getUri();
        $urlPath = $requestUri->getPath();
        $urlPath = StringDataType::substringAfter($urlPath, $this->getUrlRouteDefault() . '/');
        
        // Create instance.
        $permalink = $this->createPermalink($urlPath);
        
        // Update headers.
        $headers = $this->buildHeadersCommon();
        $headers['Location'] = self::buildAbsoluteRedirectUrl($this->getWorkbench(), $permalink);

        return new Response(self::STATUS_CODE, $headers);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::buildHeadersCommon()
     */
    protected function buildHeadersCommon() : array
    {
        try {
            $facadeHeaders = array_filter($this->getConfig()->getOption('FACADES.PERMALINKFACADE.HEADERS.COMMON')->toArray());
        } catch (ConfigOptionNotFoundError $e) {
            $facadeHeaders = [];
        }
        $commonHeaders = parent::buildHeadersCommon();
        return array_merge($commonHeaders, $facadeHeaders);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::getUrlRouteDefault()
     */
    public function getUrlRouteDefault(): string
    {
        return self::URL_ROUTE;
    }

    /**
     * Create a new permalink instance based on a URL or selector string.
     * 
     * NOTE: The created instance might not be initialized, especially if the selector
     * or URL did not point to a config alias.
     * 
     * @param string $urlPathOrSelector
     * @return PermalinkInterface
     */
    protected function createPermalink(string $urlPathOrSelector) : PermalinkInterface
    {
        $configUxon = null;
        list($alias, $innerPath) = explode('/', $urlPathOrSelector, 2);
        $selector = new PermalinkSelector($this->getWorkbench(), $alias);
        
        switch (true) {
            case  $selector->isClassname():
                $class = $selector->toString();
                break;
            case $selector->isFilepath():
                $class = PhpFilePathDataType::findClassInFile($selector->toString());
                break;
            case $selector->isAlias():
                $sheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.PERMALINK');
                
                $sheet->getColumns()->addMultiple([
                    'NAME',
                    'PROTOTYPE_FILE',
                    'CONFIG_UXON'
                ]);

                $aliasOfSelector = StringDataType::substringAfter($selector->toString(), $selector->getAppAlias() . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER);
                $sheet->getFilters()->addConditionFromString('APP__ALIAS', $selector->getAppAlias(), ComparatorDataType::EQUALS);
                $sheet->getFilters()->addConditionFromString('ALIAS', $aliasOfSelector, ComparatorDataType::EQUALS);
                $sheet->dataRead();
                
                switch ($sheet->countRows()) {
                    case 0:
                        throw new InvalidArgumentException('Could not find config for permalink with alias "' . $aliasOfSelector . '": Make sure a permalink with this alias exists and contains config data!');
                    case 1:
                        $row = $sheet->getRow();
                        break;
                    default:
                        throw new InvalidArgumentException('Permalink alias "' . $aliasOfSelector . '" is ambiguous for app "' . $selector->getAppSelector()->getAppAlias() . '": Make sure only one permalink with this alias exists in that app!');
                }

                $app = $this->getWorkbench()->getApp($selector->getAppSelector());
                $class = $app->getPrototypeClass(new PermalinkSelector($this->getWorkbench(), $row['PROTOTYPE_FILE']));
                $configUxon = UxonObject::fromJson($row['CONFIG_UXON']);
                break;
            default:
                throw new InvalidArgumentException('Could not create permalink: "' . $selector->toString() . '" is not a valid classname, filepath or alias!');
        }

        $instance = new $class($this->getWorkbench(), $alias, $configUxon);
        
        if(empty($innerPath)) {
            return $instance;
        } else {
            return $instance->withUrl($innerPath);
        }
    }

    /**
     * Returns an absolute URL to the given permalink's destination within the given workbench context.
     * 
     * @param WorkbenchInterface $workbench
     * @param PermalinkInterface $permalink
     * @return string
     */
    public static function buildAbsoluteRedirectUrl(WorkbenchInterface $workbench, PermalinkInterface $permalink) : string
    {
        return $workbench->getUrl() . $permalink->getRedirect();
    }

    /**
     *
     *
     * @param WorkbenchInterface $workbench
     * @param string             $configAlias
     * @param string             $innerUrl
     * @return string
     */
    public static function buildAbsolutePermalinkUrl(WorkbenchInterface $workbench, string $configAlias, string $innerUrl) : string
    {
        return $workbench->getUrl() . self::URL_ROUTE . '/' . $configAlias . '/' . $innerUrl;
    }
}