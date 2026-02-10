<?php

declare(strict_types=1);

namespace Kptv\IptvSync;

use Kptv\IptvSync\KpDb;
use Kptv\IptvSync\Database\WhereClause;
use Kptv\IptvSync\Database\ComparisonOperator;

class ProviderManager
{
    public function __construct(
        private readonly KpDb $db
    ) {
    }

    public function getProviders(?int $userId = null, ?int $providerId = null): array
    {
        $where = [];

        if ($userId !== null) {
            $where[] = new WhereClause('u_id', $userId, ComparisonOperator::EQ);
        }

        if ($providerId !== null) {
            $where[] = new WhereClause('id', $providerId, ComparisonOperator::EQ);
        }

        $providers = $this->db->get_all(
            table: 'stream_providers',
            where: empty($where) ? null : $where
        );

        return $providers ?? [];
    }

    public function updateLastSynced(int $providerId): void
    {
        $this->db->update(
            table: 'stream_providers',
            where: [new WhereClause('id', $providerId, ComparisonOperator::EQ)],
            data: ['sp_last_synced' => date('Y-m-d H:i:s')]
        );
    }
}
