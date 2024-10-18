<?php

namespace exface\Core\Behaviors\PlaceholderConfigs;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface;

/**
 * A template renderer config essentially validates resolvers and uxons to make sure they are compliant
 * with all `AbstractTplConfigExtension` instances added to it. 
 * 
 * Usage Example:
 * 
 * ```
 *  
 *  // Create new config.
 *  $config = new TemplateRendererConfig();
 * 
 *  // Add any extensions you wish to use.
 *  $config->addExtension(new TplConfigExtensionOldData());
 * 
 *  // You can now use the config to check for illegal placeholders.
 *  // A placeholder is illegal if it is not defined in any of the extensions
 *  // or if it is not defined for the current $context
 *  $config->checkStringForInvalidPlaceholders($context, $string)
 * 
 *  $renderedStrings = [];
 *  foreach($dataSheet->getRows() as $rowIndex => $row) {
 *      // Create a new renderer.
 *      $placeHolderRenderer = new BracketHashStringTemplateRenderer($this->getWorkbench());
 *      
 *      // Now we create the placeholder resolvers we would like to use and let the config work its magic.
 *      // It will automatically perform any necessary configurations and will only apply those resolvers
 *      // that are valid for the current $context.
 *      $this->config->applyResolversForContext($placeHolderRenderer, $context, [
 *          new DataRowPlaceholders($oldData ?? $newData, $rowIndex, TplConfigExtensionOldData::PREFIX_OLD),
 *          new DataRowPlaceholders($newData, $rowIndex, TplConfigExtensionOldData::PREFIX_NEW)
 *      ]);
 *      
 *      $renderedStrings[] = $placeHolderRenderer->render($stringToRender);
 *  }
 * 
 * ```
 * 
 */
class TemplateRendererConfig extends AbstractPhConfig
{
    private array $config = [];

    /**
     * @var AbstractTplConfigExtension[]
     */
    private array $extensions = [];

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
     * Resolvers that are not valid for the given context will simply be discarded
     * without side effects.
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
        $result = [];
        foreach ($this->extensions as $extension) {
            $result = array_merge($result, $extension->configureResolversForContext($context, $resolvers, $this));
        }
        
        foreach ($result as $resolver) {
            $renderer->addPlaceholder($resolver);
        }
    }

    /**
     * Checks a given string for invalid placeholders within the specified event context.
     * Throws an error if any invalid placeholders are detected.
     *
     * @param string $context
     * @param string $string
     * @return void
     */
    public function checkStringForInvalidPlaceholders(string $context, string $string) : void
    {
        $errors = [];
        $contextSettings = $this->extractContextSettings($context);
        
        foreach (StringDataType::findPlaceholders($string) as $placeholder) {
            $prefix = StringDataType::substringBefore($placeholder, ':', '').':';
            if(!key_exists($prefix, $contextSettings)) {
                $errors[] ='[#'.$placeholder.'#]';
            }
        }

        if(count($errors) === 0) {
            return;
        }
        
        $message = "The following placeholders are not supported for ".$context.':'.PHP_EOL;
        $message .= implode(', ', $errors);

        throw new InvalidArgumentException($message);
    }
}