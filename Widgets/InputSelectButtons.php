<?php
namespace exface\Core\Widgets;

/**
 * Allows the user to select from a list of values by toggling one or more buttons.
 * 
 * Should a facade not be able to render such buttons, it should gracefully fall back to
 * a regular select menu, radio buttons or a similar visualization.
 *
 * @author Andrej Kabachnik
 */
class InputSelectButtons extends InputSelect
{}