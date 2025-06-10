<?php
namespace exface\Core\Interfaces\TemplateRenderers;

use exface\Core\Interfaces\Debug\LogBookInterface;

/**
 * Interface for placeholder resolvers used in template renderers.
 * 
 * While the renderer takes care of parsing the template, the resolvers merely receive
 * a list of placeholder names to replace by values. Each resolver should only return
 * those placeholders, that it is actually able to get values for!
 * 
 * @author andrej.kabachnik
 *
 */
interface PlaceholderResolverInterface
{
    /**
     * Returns an array with values for those placeholders, that this class can actually resolve.
     * 
     * For example, if the requested placeholders are `["ph1", "ph2", "ph3"]` and the resolver
     * is responsible for `ph1` and `ph3`, the return values should be `["ph1" => "val1", "ph3" => "val3"]`.
     * 
     * @param string[] $placeholders
     * @param LogBookInterface|null $logbook
     * @return array
     */
    public function resolve(array $placeholders, ?LogBookInterface $logbook = null) : array;
}