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
 * This exception should be thrown on errors in the JsonDataType::validateJsonSchema() method.
 * If a json differs from the schema provided it is not valid and all schema errors should be printed.
 * 
 * Can also be for every other json schema validation error instance to build the corresponding error entry in the log.
 * 
 * @see DataTypeCastingError
 *
 * @author Miriam Seitz
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
    		switch (true) {
    			case is_array($error):
	    			$messages[] = $error['property'] . ': ' . $error['message'];
	    			break;
    			case is_string($error):
    				$messages[] = $error;
    		}
    	}
        
        return $messages;
    }
    
    public function getJson() : ?string
    {
    	return JsonDataType::prettify($this->json);
    }

    public function getErrors() : array
    {
        return $this->errors;
    }

    public function getFormattedErrors() : array
    {
        $errors = $this->getErrors();

        switch (true)
        {
            case array_key_exists('details', $errors):
                $newErrorFormat = [];
                foreach ($errors['details'] as $errorDetail) {
                    $newErrorFormat[] = [
                        'source' => $errorDetail['property'],
                        'message' => $errorDetail['message']];
                }
                $errors['details'] = $newErrorFormat;
                return $errors;
            case is_array($errors[0]):
                $newErrorFormat = [];
                foreach ($errors as $error) {
                    $newErrorFormat[] = [
                        'source' => $error['property'],
                        'message' => $error['message']];
                }
                return $newErrorFormat;
            default:
                return $errors;
        }
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