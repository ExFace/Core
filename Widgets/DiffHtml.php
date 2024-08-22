<?php

namespace exface\Core\Widgets;

use exface\Core\Widgets\DiffText;

/**
 * The DiffHtml widget compares two Html documents, rendering any detected changes in a side-by-side comparison.
 *
 * The base (or "original") text is defined by `attribute_alias` or `value` just like in any other value widget,
 * while the text to compare to (or "revision") is set by `attribute_alias_to_compare` or `value_to_compare`
 * respectively.
 *
 * ### EXAMPLE: Dialog comparing two columns, each containing HTML
 *
 * ```
 * {
 *      "widget_type": "DiffHtml",
 *      "attribute_alias": "html_old",
 *      "attribute_alias_to_compare": "html_new",
 *      "layout": "left_diff_right_new"
 * }
 * ```
 *
 * This example is used within a dialog action and compares the value in `html_old` with `html_new`, using `html_old` as the base value.
 * The (optional) property `layout` is used to specify an alternative layout.
 * @author Georg Bieger
 *
 */
class DiffHtml extends DiffText
{

}