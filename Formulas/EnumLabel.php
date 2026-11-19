<?php
namespace exface\Core\Formulas;

/**
 * Returns the label of an enum attribute's stored value. Alias of `=Format(ATTRIBUTE)`.
 * 
 * This is an alias of `=Format(ATTRIBUTE, DATATYPE)` and is provided for convenience and readability. It is used in formulas to get the label of an enum attribute's stored value.
 *
 * ### Examples
 *
 * - `=EnumLabel(ATTRIBUTE)` returns the label of the stored value of ATTRIBUTE.
 * - `=Concatenate(EnumLabel(ATTRIBUTE), '-', NOTE)` returns the label followed by the note.
 * - `=EnumLabel(ATTRIBUTE, DATATYPE)` returns the label of the stored value of ATTRIBUTE for the specified DATATYPE.
 *
 * @author Georg Bieger
 */
class EnumLabel extends Format
{
}