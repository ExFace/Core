<?php
namespace exface\Core\Templates\AbstractHttpTemplate\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Templates\HttpTemplateInterface;
use exface\Core\Exceptions\Templates\TemplateRequestParsingError;

/**
 * This PSR-15 middleware...
 * 
 * @author Andrej Kabachnik
 *
 */
class QuickSearchUrlParamReader implements MiddlewareInterface
{
    use TaskRequestTrait;
    use DataEnricherTrait;
    
    private $template = null;
    
    private $taskAttributeName = null;
    
    private $urlParamQuickSearch = null;
    
    private $getterMethodName = null;
    
    private $setterMethodName = null;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     */
    public function __construct(HttpTemplateInterface $template, $urlParamQuickSearch, $dataGetterMethod, $dataSetterMethod, $taskAttributeName = 'task')
    {
        $this->template = $template;
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
        $task = $this->getTask($request, $this->taskAttributeName, $this->template);
        
        $quick_search = $request->getQueryParams()[$this->urlParamQuickSearch];
        
        if (is_null($quick_search)) {
            $quick_search = $request->getParsedBody()[$this->urlParamQuickSearch];
        }
        
        if (is_null($quick_search) || $quick_search === '') {
            return $handler->handle($request);
        }
        
        $data_sheet = $this->getDataSheet($task, $this->getterMethodName);
        
        // Add filter for quick search
        // TODO replace this by $widget->getQuickSearchFilterCondition($value) or similar. The widget
        // should be responsible for how to perform the quick search - not the template. After all,
        // the quick search filters are defined in the UXON of the widget.
        if ($task->isTriggeredByWidget()) {
            $widget = $task->getWidgetTriggeredBy();
            $quick_search_filter = $widget->getMetaObject()->getLabelAttributeAlias();
            if ($widget->is('Data') && count($widget->getAttributesForQuickSearch()) > 0) {
                foreach ($widget->getAttributesForQuickSearch() as $attr) {
                    $quick_search_filter .= ($quick_search_filter ? EXF_LIST_SEPARATOR : '') . $attr;
                }
            }
            if ($quick_search_filter) {
                $data_sheet->addFilterFromString($quick_search_filter, $quick_search);
            } else {
                throw new TemplateRequestParsingError('Cannot perform quick search on object "' . $widget->getMetaObject()->getAliasWithNamespace() . '": either mark one of the attributes as a label in the model or set inlude_in_quick_search = true for one of the filters in the widget definition!', '6T6HSL4');
            }
        }
        
        $task = $this->updateTask($task, $this->setterMethodName, $data_sheet);
        
        return $handler->handle($request->withAttribute($this->taskAttributeName, $task));
    }
}