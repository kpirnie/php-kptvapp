<?php

declare(strict_types=1);

namespace Kptv\IptvSync\Database;

readonly class WhereClause
{
    public function __construct(
        public string $field,
        public mixed $value,
        public ComparisonOperator $operator = ComparisonOperator::EQ,
        public string $connector = 'AND'
    ) {
    }
}
