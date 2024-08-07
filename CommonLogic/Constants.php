<?php

/**
 * This file holds all global constants used in ExFace. These are part of the core source code and must not be changed for
 * customizing reasons! They are jsut here for ease of access and because of backward compatibility to older components.
 */

/**
 * @const EXF_WIDGET_VISIBILITY_NORMAL normal visibility within a tempalte
 */
const EXF_WIDGET_VISIBILITY_NORMAL = 50;

/**
 * @const EXF_WIDGET_VISIBILITY_OPTIONAL may be hidden by the facade, so the user will need to open popups etc.
 * to see the widget (especially on mobile devices)
 */
const EXF_WIDGET_VISIBILITY_OPTIONAL = 30;

/**
 * @const EXF_WIDGET_VISIBILITY_PROMOTED must be desplayed very prominently and be accessible withe extra clicks or so
 */
const EXF_WIDGET_VISIBILITY_PROMOTED = 90;

/**
 * @const EXF_WIDGET_VISIBILITY_HIDDEN hidden by default.
 * May be shown programmatically, but not by user
 */
const EXF_WIDGET_VISIBILITY_HIDDEN = 10;

/**
 * @deprecated use ComparatorDataType instead!
 * @const EXF_COMPARATOR_IN compares to each vaule in a list via EXF_COMPARATOR_IS.
 * At least one must suffice.
 */
const EXF_COMPARATOR_IN = '[';
/**
 * @deprecated use ComparatorDataType instead!
 * @var string
 */
const EXF_COMPARATOR_NOT_IN = '![';
/**
 * @deprecated use ComparatorDataType instead!
 * @const EXF_COMPARATOR_IS universal comparater - can be applied to any data type
 */
const EXF_COMPARATOR_IS = '=';
/**
 * @deprecated use ComparatorDataType instead!
 */
const EXF_COMPARATOR_IS_NOT = '!=';
/**
 * @deprecated use ComparatorDataType instead!
 * @const EXF_COMPARATOR_EQUALS compares to a single value of the same data type
 */
const EXF_COMPARATOR_EQUALS = '==';
/**
 * @deprecated use ComparatorDataType instead!
 */
const EXF_COMPARATOR_EQUALS_NOT = '!==';
/**
 * @deprecated use ComparatorDataType instead!
 */
const EXF_COMPARATOR_LESS_THAN = '<';
/**
 * @deprecated use ComparatorDataType instead!
 */
const EXF_COMPARATOR_LESS_THAN_OR_EQUALS = '<=';
/**
 * @deprecated use ComparatorDataType instead!
 */
const EXF_COMPARATOR_GREATER_THAN = '>';
/**
 * @deprecated use ComparatorDataType instead!
 */
const EXF_COMPARATOR_GREATER_THAN_OR_EQUALS = '>=';

/*
 * Lists
 */ 

const EXF_LIST_SEPARATOR = ',';

/*
 * Logical operators
 */

const EXF_LOGICAL_AND = 'AND';

const EXF_LOGICAL_OR = 'OR';

const EXF_LOGICAL_XOR = 'XOR';

const EXF_LOGICAL_NOT = 'NOT';

const EXF_LOGICAL_NULL = 'NULL';

/*
 * Alignment options
 */

const EXF_ALIGN_DEFAULT = 'default';

const EXF_ALIGN_OPPOSITE = 'opposite';

const EXF_ALIGN_LEFT = 'left';

const EXF_ALIGN_RIGHT = 'right';

const EXF_ALIGN_CENTER = 'center';

/*
 * Mouse action types
 */ 

const EXF_MOUSE_ACTION_DOUBLE_CLICK = 'double_click';

const EXF_MOUSE_ACTION_LEFT_CLICK = 'left_click';

const EXF_MOUSE_ACTION_RIGHT_CLICK = 'right_click';

const EXF_MOUSE_ACTION_LONG_TAP = 'long_tap';

/*
 * Text properties
 */

const EXF_TEXT_SIZE_SMALL = 'small';

const EXF_TEXT_SIZE_NORMAL = 'normal';

const EXF_TEXT_SIZE_BIG = 'big';

const EXF_TEXT_STYLE_BOLD = 'bold';

const EXF_TEXT_STYLE_NORMAL = 'normal';

const EXF_TEXT_STYLE_STRIKETHROUGH = 'strikethrough';

const EXF_TEXT_STYLE_UNDERLINE = 'underline';

const EXF_TEXT_STYLE_ITALIC = 'italic';

?>