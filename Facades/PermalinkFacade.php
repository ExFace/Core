<?php
namespace exface\Core\Facades;

use exface\Core\CommonLogic\Selectors\PermalinkSelector;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\PhpFilePathDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\Configuration\ConfigOptionNotFoundError;
use exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Permalinks\PermalinkInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Interfaces\Selectors\PermalinkSelectorInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Redirects the browser to a dynamic URL from a long-term
 * 
 * Usage:
 * 
 * - api/permalink/exface.core.show_object/<uid_of_object> - open the object editor
 * - api/permalink/my.app.run_data_flow/<uid_of_flow>/<param1>/<param2> - run a DataFlow with parameters
 * 
 * @author Andrej Kabachnik
 *
 */
class PermalinkFacade extends AbstractHttpFacade
{

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::createResponse()
     */
    protected function createResponse(ServerRequestInterface $request) : ResponseInterface
    {
        $requestUri = $request->getUri();
        /* api/permalink/exface.Core.show_object/0x812345aasdf */
        $path = $requestUri->getPath();
        /* exface.Core.show_object/0x812345aasdf */
        $path = StringDataType::substringAfter($path, $this->getUrlRouteDefault());
        list($alias, $innerPath) = explode('/', $path, 2);

        $linkPrototype = $this->getPermalink(new PermalinkSelector($this->getWorkbench(), $alias));
        $permalink = $linkPrototype->withUrl($innerPath);
        $redirect = $permalink->getRedirect();

        $headers = $this->buildHeadersCommon();
        $headers['Location'] = $redirect;
        switch (true) {

            default:
                // TODO add a redirect header?
                $response = new Response(200, $headers);
                break;
        }

        return $response;
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
        return 'api/permalink';
    }

    protected function getPermalink(PermalinkSelectorInterface $selector) : PermalinkInterface
    {
        switch (true) {
            case $selector->isClassname():
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
                $row = $sheet->getRow(0);
                $filePath = $row['PROTOTYPE_FILE'];
                $class = PhpFilePathDataType::findClassInFile($filePath);
                $configUxon = UxonObject::fromJson($row['CONFIG_UXON']);
                $configUxon->setProperty('name',  $row['NAME']);
                $configUxon->setProperty('alias',  $selector->toString());
                break;
        }

        $instance = new $class($this->getWorkbench(), $configUxon);
        return $instance;
    }
}