<?php
namespace exface\Core\Facades\AbstractHttpFacade\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Exceptions\Facades\FacadeRequestParsingError;
use exface\Core\Facades\AbstractHttpFacade\Middleware\Traits\DataEnricherTrait;
use exface\Core\Facades\AbstractHttpFacade\Middleware\Traits\TaskRequestTrait;
use exface\Core\Interfaces\Widgets\iHaveQuickSearch;

/**
 * This PSR-15 middleware transforms the specified URL or body parameter into a quick search
 * filter set for a data sheet in the HttpTask within the request.
 * 
 * @author Andrej Kabachnik
 *
 */
class QuickSearchUrlParamReader implements MiddlewareInterface
{
    use TaskRequestTrait;
    use DataEnricherTrait;
    
    private $facade = null;
    
    private $taskAttributeName = null;
    
    private $urlParamQuickSearch = null;
    
    private $getterMethodName = null;
    
    private $setterMethodName = null;
    
    /**
     * 
     * @param HttpFacadeInterface $facade
     * @param string $urlParamQuickSearch
     * @param string $dataGetterMethod
     * @param string $dataSetterMethod
     * @param string $taskAttributeName
     */
    public function __construct(HttpFacadeInterface $facade, string $urlParamQuickSearch, string $dataGetterMethod = 'getInputData', string $dataSetterMethod = 'setInputData', string $taskAttributeName = 'task')
    {
        $this->facade = $facade;
        $this->taskAttributeName = $taskAttributeName;
        $this->urlParamQuickSearch = $urlParamQuickSearch;
        $this->getterMethodName = $dataGetterMethod;
        $this->setterMethodName = $dataSetterMethod;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $task = $this->getTask($request, $this->taskAttributeName, $this->facade);
        
        $quick_search = $request->getQueryParams()[$this->urlParamQuickSearch] ?? null;
        
        if (is_null($quick_search)) {
            $quick_search = $request->getParsedBody()[$this->urlParamQuickSearch];
        }
        
        if (is_null($quick_search) || $quick_search === '') {
            return $handler->handle($request);
        }
        
        $data_sheet = $this->getDataSheet($task, $this->getterMethodName);
        
        // Add filter for quick search
        // TODO replace this by $widget->getQuickSearchFilterCondition($value) or similar. The widget
        // should be responsible for how to perform the quick search - not the facade. After all,
        // the quick search filters are defined in the UXON of the widget.
        if ($task->isTriggeredByWidget()) {
            $widget = $task->getWidgetTriggeredBy();
            if ($widget instanceof iHaveQuickSearch) {
                $quickSearchConditionGroup = $widget->getQuickSearchConditionGroup($quick_search);
                if (! $quickSearchConditionGroup->isEmpty()) {
                    $data_sheet->getFilters()->addNestedGroup($quickSearchConditionGroup);
                }
            } elseif ($widget->getMetaObject()->hasLabelAttribute()) {
                $data_sheet->getFilters()->addConditionFromString($widget->getMetaObject()->getLabelAttributeAlias(), $quick_search);
            } else {
                throw new FacadeRequestParsingError('Cannot perform quick search on object "' . $widget->getMetaObject()->getAliasWithNamespace() . '": either mark one of the attributes as a label in the model or set inlude_in_quick_search = true for one of the filters in the widget definition!', '6T6HSL4');
            }
        }
        
        $task = $this->updateTask($task, $this->setterMethodName, $data_sheet);
        
        return $handler->handle($request->withAttribute($this->taskAttributeName, $task));
    }
}