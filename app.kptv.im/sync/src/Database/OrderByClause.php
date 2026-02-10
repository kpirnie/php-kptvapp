<?php

declare(strict_types=1);

namespace Kptv\IptvSync\Database;

readonly class OrderByClause
{
    public function __construct(
        public string $column,
        public string $direction = 'ASC'
    ) {
    }

    public function __toString(): string
    {
        $direction = strtoupper($this->direction) === 'DESC' ? 'DESC' : 'ASC';
        return "{$this->column} {$direction}";
    }
}
