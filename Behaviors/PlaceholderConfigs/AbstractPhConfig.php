<?php

namespace exface\Core\Behaviors\PlaceholderConfigs;

abstract class AbstractPhConfig
{
    /**
     * An array that defines what placeholders will be resolved for a given
     * context. It serves as a whitelist, meaning that only explicitly defined placeholders
     * are going to work.
     *
     * Override this method to adapt it to the needs of your behavior. Adhere to the following structure:
     *
     * ```
     *
     *  protected function getConfig() : array
     *  {
     *    return [
     *          'contextA' => [
     *              'prefixA',
     *              'prefixB'
     *              ...
     *          ],
     *          'contextB' => [
     *              'prefixA',
     *              'prefixC'  // You can vary prefixes between contexts.
     *              ...
     *          ],
     *      ];
     *  }
     * 
     * ```
     * 
     * NOTE: Context can be any string. You could for example use event classes with `SomeEvent::class` as context. 
     * 
     * @return array
     */
    public abstract function getConfig() : array;

    /**
     * Returns a list of legal placeholders for a given context.
     *
     * @param string $context
     * @return array
     */
    public function extractContextSettings(string $context) : array
    {
        $config = $this->getConfig();
        if(!key_exists($context, $config)) {
            return [];
        }

        return $config[$context];
    }
}