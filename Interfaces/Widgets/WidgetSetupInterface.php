<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\Mutations\MutationInterface;

/**
 * Common interface for widget setup models.
 * 
 * Setups are closely related to mutations. They also mutate the model of a widget, but they have much stricter rules. 
 * You may say, setups are simplified mutations, that even an end user can apply. Consequently, PHP classes for widget
 * setups implement Mutation Interface and actually can be used within the regular mutation engine too (but other
 * mutations cannot be used as setups).
 *
 * For every widget type, that supports setups, there should be is a separate setup prototype class implementing this
 * interface. The different things users can configure - columns, filters, etc. - are modeled by mutation rule
 * prototypes within that mutation. This way, setups are fully compatible with mutations and can be even part of them 
 * if needed.
 * 
 * @author Andrej Kabachnik
 */
interface WidgetSetupInterface extends MutationInterface
{
}