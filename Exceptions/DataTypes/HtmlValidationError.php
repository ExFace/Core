<?php
namespace exface\Core\Exceptions\DataTypes;

use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\JsonDataType;

/**
 * Exception thrown if a value does not fit a data type's model.
 *
 * This exception should be thrown on errors in the HtmlDataType::validateHtml() method.
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

    public function addError(\LibXMLError $error) : HTmlValidationError
    {
        $this->errors[] = $error;
        return $this;
    }

    public function getErrorMessages() : array
    {
        $messages = null;
        foreach ($this->errors as $error) {
            $messages[] = $this->formatError($error);
        }

        return $messages;
    }

    private function formatError(\LibXMLError $error) : string
    {
        return 'Line '.$error->line.' - '.self::ERROR_LEVELS[$error->level].' '.$error->message;
    }

    public function getErrors() : array
    {
        return $this->errors;
    }

    public function getHtml() : string
    {
        return $this->html;
    }

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

