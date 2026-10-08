<?php

namespace Granada\Orm;

/**
 * How a condition connects to the conditions before it.
 */
enum ConnectedBy: string
{
    case And = 'AND';
    case Or  = 'OR';
}
