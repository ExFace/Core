<?php
namespace exface\Core\Templates\AbstractAjaxTemplate;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\CommonLogic\WidgetLink;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Templates\AbstractAjaxTemplate\Elements\AbstractJqueryElement;
use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Events\WidgetEvent;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Exceptions\InternalError;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\Templates\AbstractAjaxTemplate\Formatters\JsNumberFormatter;
use exface\Core\DataTypes\DateDataType;
use exface\Core\Templates\AbstractAjaxTemplate\Formatters\JsDateFormatter;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Templates\AbstractAjaxTemplate\Formatters\JsTransparentFormatter;
use exface\Core\Templates\AbstractAjaxTemplate\Interfaces\JsDataTypeFormatterInterface;
use exface\Core\Templates\AbstractAjaxTemplate\Formatters\JsEnumFormatter;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Templates\AbstractAjaxTemplate\Formatters\JsBooleanFormatter;
use exface\Core\Templates\AbstractHttpTemplate\AbstractHttpTemplate;
use Psr\Http\Server\MiddlewareInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use exface\Core\Interfaces\Tasks\TaskResultInterface;
use exface\Core\Interfaces\Tasks\TaskResultWidgetInterface;

abstract class AbstractAjaxTemplate extends AbstractHttpTemplate
{
    const MODE_HEAD = 'HEAD';
    const MODE_BODY = 'BODY';
    const MODE_FULL = '';

    private $elements = array();
    
    /**
     * [ widget_type => qualified class name]
     * @var array
     */
    private $classes_by_widget_type = [];

    private $class_prefix = '';

    private $class_namespace = '';
    
    private $data_type_formatters = [];

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Templates\AbstractTemplate\AbstractTemplate::init()
     */
    protected function init()
    {
        parent::init();
        $this->getWorkbench()->eventManager()->addListener('#.Widget.Remove.After', function (WidgetEvent $event) {
            $this->removeElement($event->getWidget());
        });
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractHttpTemplate\AbstractHttpTemplate::handle()
     */
    public function handle(ServerRequestInterface $request, $pageSelectorString = null, $actionSelectorString = null, $renderingMode = self::MODE_FULL) : ResponseInterface
    {
        if (! is_null($pageSelectorString)) {
            $request = $request->withAttribute($this->getRequestAttributeForPage(), $pageSelectorString);
        }
        if (! is_null($actionSelectorString)) {
            $request = $request->withAttribute($this->getRequestAttributeForAction(), $actionSelectorString);
        }
        return parent::handle($request);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Templates\AbstractTemplate\AbstractTemplate::buildWidget()
     */
    function buildWidget(WidgetInterface $widget)
    {
        $output = '';
        try {
            $output = $this->generateHtml($widget);
            $js = $this->generateJs($widget);
        } catch (\Throwable $e) {
            if ($this->getWorkbench()->getConfig()->getOption('DEBUG.DISABLE_TEMPLATE_ERROR_HANDLERS')) {
                throw $e;
            }
            
            if (! $e instanceof ExceptionInterface){
                $e = new InternalError($e->getMessage(), null, $e);
            }
            
            $this->setResponseFromError($e, $widget->getPage());
            $output = $this->getResponse();
        }
        if ($js) {
            $output .= "\n" . '<script type="text/javascript">' . $js . '</script>';
        }
        
        return $output;
    }

    /**
     * Generates the JavaScript for a given Widget
     *
     * @param \exface\Core\Widgets\AbstractWidget $widget            
     */
    public function generateJs(\exface\Core\Widgets\AbstractWidget $widget)
    {
        $instance = $this->getElement($widget);
        return $instance->generateJs();
    }

    /**
     * Generates the HTML for a given Widget
     *
     * @param \exface\Core\Widgets\AbstractWidget $widget            
     */
    public function generateHtml(\exface\Core\Widgets\AbstractWidget $widget)
    {
        $instance = $this->getElement($widget);
        return $instance->generateHtml();
    }

    /**
     * Generates the declaration of the JavaScript sources
     *
     * @return string
     */
    public function buildIncludes(WidgetInterface $widget)
    {
        try {
            $instance = $this->getElement($widget);
            $result = implode("\n", array_unique($instance->generateHeaders()));
        } catch (ErrorExceptionInterface $e) {
            // TODO Is there a way to display errors in the header nicely?
            /*
             * $ui = $this->getWorkbench()->ui();
             * $page = UiPageFactory::create($ui, '');
             * return $this->getWorkbench()->getDebugger()->printException($e, false);
             */
            throw $e;
        }
        return $result;
    }

    /**
     * Creates a template element for a given ExFace widget.
     * Elements are cached within the template engine, so multiple calls to this method do
     * not cause the element to get recreated from scratch. This improves performance.
     *
     * @param WidgetInterface $widget            
     * @return AbstractJqueryElement
     */
    public function getElement(\exface\Core\Widgets\AbstractWidget $widget)
    {
        if (! array_key_exists($widget->getPage()->getAliasWithNamespace(), $this->elements) || ! array_key_exists($widget->getId(), $this->elements[$widget->getPage()->getAliasWithNamespace()])) {
            $elem_class = $this->getClass($widget);
            $instance = new $elem_class($widget, $this);
            // $this->elements[$widget->getPage()->getAliasWithNamespace()][$widget->getId()] = $instance;
        }
        
        return $this->elements[$widget->getPage()->getAliasWithNamespace()][$widget->getId()];
    }

    public function removeElement(AbstractWidget $widget)
    {
        unset($this->elements[$widget->getPage()->getAliasWithNamespace()][$widget->getId()]);
    }

    public function registerElement($element)
    {
        $this->elements[$element->getWidget()->getPage()->getAliasWithNamespace()][$element->getWidget()->getId()] = $element;
        return $this;
    }

    protected function getClass(WidgetInterface $widget)
    {
        $elem_class = $this->classes_by_widget_type[$widget->getWidgetType()];
        if (is_null($elem_class)) {
            $elem_class_prefix = $this->getClassNamespace() . '\\Elements\\' . $this->getClassPrefix();
            $elem_class = $elem_class_prefix . $widget->getWidgetType();
            if (! class_exists($elem_class)) {
                $widget_class = get_parent_class($widget);
                $elem_class = $elem_class_prefix . AbstractWidget::getWidgetTypeFromClass($widget_class);
                while (! class_exists($elem_class)) {
                    if ($widget_class = get_parent_class($widget_class)) {
                        $elem_class = $elem_class_prefix . AbstractWidget::getWidgetTypeFromClass($widget_class);
                    } else {
                        break;
                    }
                }
                
                if (class_exists($elem_class)) {
                    $reflection = new \ReflectionClass($elem_class);
                    if ($reflection->isAbstract()) {
                        $elem_class = $elem_class_prefix . 'BasicElement';
                    }
                } else {
                    // if the required widget is not found, create an abstract widget instead
                    $elem_class = $elem_class_prefix . 'BasicElement';
                }
            }
            $this->classes_by_widget_type[$widget->getWidgetType()] = $elem_class;
        }
        return $elem_class;
    }

    /**
     * Creates a template element for a widget of the give resource, specified by the
     * widget's ID.
     * It's just a shortcut in case you do not have the widget object at
     * hand, but know it's ID and the resource, where it resides.
     *
     * @param string $widget_id            
     * @param UiPageInterface $page            
     * @return AbstractJqueryElement
     */
    public function getElementByWidgetId($widget_id, UiPageInterface $page)
    {
        if ($elem = $this->elements[$page->getAliasWithNamespace()][$widget_id]) {
            return $elem;
        } elseif ($widget = $page->getWidget($widget_id)) {
            return $this->getElement($widget);
        } else {
            return false;
        }
    }

    public function getElementFromWidgetLink(WidgetLink $link)
    {
        return $this->getElementByWidgetId($link->getWidgetId(), $link->getPage());
    }

    public function createLinkInternal($page_or_id_or_alias, $url_params = '')
    {
        return $this->getWorkbench()->getCMS()->createLinkInternal($page_or_id_or_alias, $url_params);
    }

    public function getClassPrefix()
    {
        return $this->class_prefix;
    }

    public function setClassPrefix($value)
    {
        $this->class_prefix = $value;
        return $this;
    }

    public function getClassNamespace()
    {
        return $this->class_namespace;
    }

    public function setClassNamespace($value)
    {
        $this->class_namespace = $value;
    }
    
    /**
     * Returns the data type formatter for the given data type.
     * 
     * @param DataTypeInterface $dataType
     * @return JsDataTypeFormatterInterface
     */
    public function getDataTypeFormatter(DataTypeInterface $dataType)
    {
        switch (true) {
            case $dataType instanceof EnumDataTypeInterface: return new JsEnumFormatter($dataType);
            case $dataType instanceof NumberDataType: return new JsNumberFormatter($dataType);
            case $dataType instanceof DateDataType: return new JsDateFormatter($dataType);
            case $dataType instanceof BooleanDataType: return new JsBooleanFormatter($dataType);
        }
        return new JsTransparentFormatter($dataType);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractHttpTemplate\AbstractHttpTemplate::getMiddlewareTaskReader()
     */
    protected function getMiddlewareTaskReader() : MiddlewareInterface
    {
        $reader = parent::getMiddlewareTaskReader();
        
        $reader->setParamNameAction('action');
        $reader->setParamNameObject('object');
        $reader->setParamNamePage('resource');
        $reader->setParamNameWidget('element');
        $reader->setParamNameData('data');
        $reader->setParamNamePrefill('prefill');
        
        $reader->setFilterParser(function(array $params, DataSheetInterface $dataSheet) {
            // Filters a passed as request values with a special prefix: fltr01_, fltr02_, etc.
            foreach ($params as $var => $val) {
                if (strpos($var, 'fltr') === 0) {
                    $dataSheet->addFilterFromString(urldecode(substr($var, 7)), $val);
                }
            }
            
            return $dataSheet;
        });
        
        $reader->setSorterParser(function(array $params, DataSheetInterface $dataSheet) {
            $order = isset($params['order']) ? strval($params['order']) : null;
            $sort_by = isset($params['sort']) ? strval($params['sort']) : null;
            if (! is_null($sort_by) && ! is_null($order)) {
                $sort_by = explode(',', $sort_by);
                $order = explode(',', $order);
                foreach ($sort_by as $nr => $sort) {
                    $dataSheet->getSorters()->addFromString($sort, $order[$nr]);
                }
            }
            return $dataSheet;
        });
        
        $reader->setPaginationParser(function(array $params, DataSheetInterface $dataSheet) {
            $page_length = isset($params['rows']) ? intval($params['rows']) : 0;
            $page_nr = isset($params['page']) ? intval($params['page']) : 1;
            $dataSheet->setRowOffset(($page_nr - 1) * $page_length);
            $dataSheet->setRowsOnPage($page_length);
            return $dataSheet;
        });
        
        return $reader;
    }
    
    protected function createResponse(ServerRequestInterface $request, TaskResultInterface $result)
    {
        $response = parent::createResponse($request, $result);
        if ($result instanceof TaskResultWidgetInterface) {
            $mode = $request->getAttribute($this->getRequestAttributeForRenderingMode(), static::MODE_FULL);
            $widget = $result->getWidget();
            switch ($mode) {
                case static::MODE_HEAD:
                    $body = $this->buildIncludes($widget);
                    break;
                case static::MODE_BODY:
                    $body = $this->buildWidget($widget);
                    break;
                case static::MODE_FULL:
                    $body = $this->buildIncludes($widget) . "\n" . $this->buildWidget($widget);
            }
            return $response->withBody(\GuzzleHttp\Psr7\stream_for($body));            
        }
        return $response;
    }
    
    protected function createResponseError(ServerRequestInterface $request, \Throwable $exception, UiPageInterface $page = null)
    {
        $mode = $request->getAttribute($this->getRequestAttributeForRenderingMode(), static::MODE_FULL);
        if ($mode === static::MODE_HEAD) {
            throw $exception;
        }
        
        return parent::createResponse($request, $exception, $page);
    }
}
?>