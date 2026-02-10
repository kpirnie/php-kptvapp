<?php

declare(strict_types=1);

namespace Kptv\IptvSync\Database;

enum ComparisonOperator: string
{
    case EQ = '=';
    case NE = '!=';
    case LT = '<';
    case GT = '>';
    case LTE = '<=';
    case GTE = '>=';
    case LIKE = 'LIKE';
    case NOT_LIKE = 'NOT LIKE';
    case IN = 'IN';
    case NOT_IN = 'NOT IN';
    case IS_NULL = 'IS NULL';
    case IS_NOT_NULL = 'IS NOT NULL';
    case BETWEEN = 'BETWEEN';
    case REGEXP = 'REGEXP';
    case NOT_REGEXP = 'NOT REGEXP';
}
