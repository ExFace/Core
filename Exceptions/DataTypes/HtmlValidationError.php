<?php
namespace exface\Core\Exceptions\DataTypes;

use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;

/**
 * Exception thrown if an HTML fragment is validated and errors are found.
 *
 * @see DataTypeCastingError
 *
 * @author Miriam Seitz, Georg Bieger
 *
 */
class HtmlValidationError extends UnexpectedValueException
{
    private const ERROR_LEVELS = array (
        LIBXML_ERR_WARNING => "WARNING",
        LIBXML_ERR_ERROR => "ERROR",
        LIBXML_ERR_FATAL => "FATAL",
    );

    private array $errors = [];
    private string $html;

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Exceptions\DataTypeExceptionInterface::__construct()
     */
    public function __construct($message, $alias = null, $previous = null, $html = null, array $validationErrors = null)
    {
        parent::__construct($message, $alias, $previous);
        $this->html = $html;
        $this->errors = $validationErrors;
    }

    /**
     * 
     * @param \LibXMLError $error
     * @return HTmlValidationError
     */
    public function addError(\LibXMLError $error) : HTmlValidationError
    {
        $this->errors[] = $error;
        return $this;
    }

    /**
     * 
     * @return string[]
     */
    public function getErrorMessages() : array
    {
        $messages = null;
        foreach ($this->errors as $error) {
            $messages[$error->line] = $this->formatError($error);
        }

        return $messages;
    }

    /**
     * 
     * @param \LibXMLError $error
     * @return string
     */
    private function formatError(\LibXMLError $error) : string
    {
        return 'Line '.$error->line.' - '.self::ERROR_LEVELS[$error->level].' '.$error->message;
    }

    /**
     * 
     * @return \LibXMLError[]
     */
    public function getErrors() : array
    {
        return $this->errors;
    }

    /**
     * 
     * @return string
     */
    public function getHtml() : string
    {
        return $this->html;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debugWidget)
    {
        $debugWidget = parent::createDebugWidget($debugWidget);

        $markdown = 'The following errors where found when validating the given HTML:' . PHP_EOL;
        foreach ($this->getErrorMessages() as $message){
            $markdown .= '- ' . $message . PHP_EOL;
        }

        $markdown .= PHP_EOL . 'HTML: ' . PHP_EOL;
        $markdown .= <<<MD
```html
{$this->html}
```
MD;

        // Add a debug tab if there is debug information available
        $debugWidget->addTab(WidgetFactory::createFromUxonInParent(
            $debugWidget,
            new UxonObject([
                'widget_type' => 'Tab',
                'caption' => 'Validation errors',
                'widgets' => [
                    [
                        'widget_type' => 'Markdown',
                        'width' => '100%',
                        'height' => '100%',
                        'value' => $markdown
                    ]
                ]
            ])));
        return $debugWidget;
    }
}