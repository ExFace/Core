<?php

namespace exface\Core\Behaviors\PlaceholderConfigs;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface;

class TemplateRendererConfig extends AbstractPhConfig
{
    private array $config;

    /**
     * @var AbstractTplConfigExtension[]
     */
    private array $extensions;

    /**
     * Extends this config with a specified placeholder config. 
     * 
     * This operation is non-reversible and order dependent: If two extensions
     * share the same context, the later extension might overwrite individual
     * settings within that context:
     * 
     * ```
     *  
     *  // PSEUDOCODE
     * 
     *  // Before extension.
     *  settings: [
     *      contextA = [
     *          prefix1 => cfgExtensionType1,
     *          prefix2 => cfgExtensionType1
     *      ]
     *  ]
     * 
     *  extendWithPhConfig([
     *      contextA = [
     *          prefix1 => cfgExtensionType2, // This will overwrite contextA['ph1'].
     *          prefix3 => cfgExtensionType2  // This will be appended to contextA[].
     *      ],
     *      contextB = [
     *          prefix1 => cfgExtensionType2 // This is a new context and will be appended.
     *      ]
     *  ]);
     * 
     *  // After extension.
     *  settings: [
     *       contextA = [
     *           prefix1 => cfgExtensionType2, // Overwritten.
     *           prefix2 => cfgExtensionType1, // Unchanged.
     *           prefix3 => cfgExtensionType2  // Appended.
     *       ],
     *       contextB = [
     *           prefix1 => cfgExtensionType2 // New context appended.
     *       ]
     *  ]
     * 
     * ```
     *
     * @param AbstractPhConfig $extension
     * @return void
     */
    public function addExtension(AbstractPhConfig $extension) : void
    {
        if(in_array($extension, $this->extensions, true)) {
            return;
        }
        
        $extensionClass = get_class($extension);
        foreach ($extension->getConfig() as $context => $config) {
            foreach ($config as $prefix) {
                $this->config[$context][$prefix] = $extensionClass;
            }
        }
        
        $this->extensions[] = $extension;
    }

    /**
     * Returns the config of this instance.
     * 
     * The config has the following structure:
     * 
     * ```
     * 
     *  $config = [
     *      string $context = [
     *          string $prefix => string $extensionClass 
     *      ],
     *      ...
     *  ]
     * 
     *  ```
     * 
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Automagically configures the resolver template and applies the resulting
     * resolver instances to the specified template renderer.
     *
     * @param TemplateRendererInterface      $renderer
     * @param PlaceholderResolverInterface[] $resolvers
     * @param string                         $context
     * @return void
     */
    public function applyResolversForContext(
        TemplateRendererInterface &$renderer, 
        string $context,
        array $resolvers) : void
    {
        $resolvers = [];
        foreach ($this->extensions as $extension) {
            $resolvers[] = $extension->configureResolversForContext($context, $resolvers, $this);
        }
        
        foreach ($resolvers as $resolver) {
            $renderer->addPlaceholder($resolver);
        }
    }

    /**
     * Checks a given UXON for invalid placeholders within the specified event context.
     * Throws an error if any invalid UXONs are detected.
     *
     * @param UxonObject $uxon
     * @param string     $context
     * @return string
     */
    public function checkUxonForInvalidPlaceholders(string $context, UxonObject $uxon) : string
    {
        $errors = [];
        $json = $uxon->toJson();
        $contextSettings = $this->extractContextSettings($context);
        
        foreach (StringDataType::findPlaceholders($json) as $placeholder) {
            $prefix = StringDataType::substringBefore($placeholder, ':', '').':';
            if(!key_exists($prefix, $contextSettings)) {
                $errors[] ='[#'.$placeholder.'#]';
            }
        }

        if(count($errors) === 0) {
            return $json;
        }
        
        $message = "The following placeholders are not supported for ".$context.':'.PHP_EOL;
        $message .= implode(', ', $errors);

        throw new InvalidArgumentException($message);
    }
}