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
 * This exception should be thrown on errors in the DataType::parse() methods.
 * If a value is so much different, that it even cannot be casted to a data
 * type, a DataTypeCastingError will be raised instead of a validation error.
 * 
 * @see DataTypeCastingError
 *
 * @author Andrej Kabachnik
 *        
 */
class JsonSchemaValidationError extends UnexpectedValueException
{
    private $errors = [];
    private $json;
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Exceptions\DataTypeExceptionInterface::__construct()
     */
    public function __construct(array $validationErrors, $message, $alias = null, $previous = null, $json = null)
    {
        parent::__construct($message, $alias, $previous);
        $this->json = $json;
        $this->errors = $validationErrors;
    }
    
    public function addError(string $message) : JsonSchemaValidationError
    {
        $this->errors[] = $message;
        return $this;
    }
    
    public function getValidationErrorMessages() : array
    {
    	foreach ($this->errors as $error){
    		$messages[] = $error['message'];
    	}
        
        return $messages;
    }
    
    public function getErrors() : array 
    {
    	return $this->errors;
    }
    
    public function getJson() : string 
    {
    	return JsonDataType::prettify($this->json);
    }
    
    public function createDebugWidget(DebugMessage $debugWidget)
    {
    	$debugWidget = parent::createDebugWidget($debugWidget);
    	
    	$markdown = 'The following errors where found when validating the given JSON against the corresponding schema.' . PHP_EOL;
    	foreach ($this->getValidationErrorMessages() as $message){
    		$markdown .= '- ' . $message . PHP_EOL;
    	}
    	
    	$markdown .= PHP_EOL . 'Json: ' . PHP_EOL;
    	$markdown .= <<<MD
```json
{$this->getJson()}
```
MD;
    	
    	// Add a debug tab if there is debug information available
    	$debugWidget->addTab(WidgetFactory::createFromUxonInParent(
    		$debugWidget,
    		new UxonObject([
    			'widget_type' => 'Tab',
    			'caption' => 'Schema valdiation errors',
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
?>