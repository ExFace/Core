<?php

namespace exface\Core\Exceptions\Actions;

/**
 * Exception thrown if an action excepcts specific content in the input data sheet and that content is missing.
 *
 * This exceptions should be thrown, for example, if an action counts on values in certain columns and the data
 * sheet is empty or does not have the required columns.
 *
 * @author Andrej Kabachnik
 *        
 */
class ActionInputMissingError extends ActionInputError
{
}
