<?php

namespace exface\Core\Behaviors\PlaceholderValidation;

use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\TemplateRenderer\PlaceholderNotFoundError;
use exface\Core\Interfaces\TemplateRenderers\PrefixValidatorInterface;
use exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface;
use Throwable;
use Wingu\OctopusCore\Reflection\ReflectionClass;

class TemplateValidator implements PrefixValidatorInterface
{
    /**
     * @var PrefixValidatorInterface[]
     */
    private array $validators = [];

    /**
     * @param PrefixValidatorInterface[] $validators
     */
    public function __construct(array $validators)
    {
        if(!empty($validators)) {
            $validators = array_filter($validators);
            $validators = array_unique($validators);
            $this->validators = array_unique($validators);
        }
    }

    public function addValidator(PrefixValidatorInterface $validator) : static
    {
        if(!in_array($validator, $this->validators)) {
            $this->validators[] = $validator;
        }
        
        return $this;
    }
    
    public function removeValidator(PrefixValidatorInterface $validator) : static
    {
        $index = array_search($validator, $this->validators);
        if($index !== false) {
            array_splice($this->validators, $index, 1);
        }
        
        return $this;
    }

    /**
     * @throws Throwable
     */
    public function TryRenderTemplate(TemplateRendererInterface $renderer, string $template, $context) : string
    {
        try {
            $renderedJson = $renderer->render($template);
        } catch (Throwable $e) {
            $violations = $this->validateTemplateForContext($template, $context);
            if(empty($violations)) {
                throw $e;
            } else {
                if(is_string($context)) {
                    $contextName = $context;
                } else {
                    $contextName = (new ReflectionClass($context))->getShortName();
                }
                
                $message = "The following placeholders are not supported for '".$contextName."':";
                $illegalPlaceholders = implode(', ', $violations);
                $error = new PlaceholderNotFoundError($illegalPlaceholders, $message.PHP_EOL.$illegalPlaceholders, null, $e, $template);
                $error->setUseExceptionMessageAsTitle(true);
                
                throw $error;
            }
        }
        
        return $renderedJson;
    }
    
    /**
     * Checks a given string for invalid placeholders within the specified event context.
     * Returns all detected violations.
     *
     * @param string $templateString
     * @param        $context
     * @return array
     */
    public function validateTemplateForContext(string $templateString, $context) : array
    {
        $violations = [];
        foreach (StringDataType::findPlaceholders($templateString) as $placeholder) {
            $prefix = StringDataType::substringBefore($placeholder, ':', '').':';
            if(!$this->isValidPrefixForContext($prefix, $context)) {
                $violations[] = $placeholder;
            }
        }

        return $violations;
    }

    public function isValidPrefixForContext(string $prefix, $context): bool
    {
        foreach ($this->validators as $validator) {
            if(!$validator->isValidPrefixForContext($prefix, $context)) {
                return false;
            }
        }
        
        return true;
    }
}