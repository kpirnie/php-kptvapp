<?php

declare(strict_types=1);

namespace Kptv\IptvSync;

use Kptv\IptvSync\KpDb;
use Kptv\IptvSync\Database\WhereClause;
use Kptv\IptvSync\Database\ComparisonOperator;
use Kptv\IptvSync\Parsers\ProviderFactory;

class MissingChecker
{
    public function __construct(
        private readonly KpDb $db,
        private readonly bool $checkAll = false
    ) {
    }

    public function checkProvider(array $provider): array
    {
        $providerId = $provider['id'];
        $userId = $provider['u_id'];

        // Get streams from provider
        $parser = ProviderFactory::create($provider);
        $providerStreams = $parser->fetchStreams();

        // Create lookup set
        $providerUris = array_flip(array_column($providerStreams, 's_stream_uri'));

        // Build where clauses
        $where = [
            new WhereClause('u_id', $userId, ComparisonOperator::EQ),
            new WhereClause('p_id', $providerId, ComparisonOperator::EQ)
        ];

        // Only filter by active if not checking all
        if (!$this->checkAll) {
            $where[] = new WhereClause('s_active', 1, ComparisonOperator::EQ);
        }

        // Get streams from database
        $dbStreams = $this->db->get_all(
            table: 'streams',
            columns: ['id', 's_stream_uri', 's_orig_name'],
            where: $where
        );

        if (empty($dbStreams)) {
            return [];
        }

        // Find missing
        $missing = [];
        foreach ($dbStreams as $stream) {
            if (!isset($providerUris[$stream['s_stream_uri']])) {
                $missing[] = $stream;
            }
        }

        // Record missing streams
        if (!empty($missing)) {
            $this->recordMissing($userId, $providerId, $missing);
        }

        return $missing;
    }

    private function recordMissing(int $userId, int $providerId, array $missing): void
    {
        $records = [];

        foreach ($missing as $stream) {
            $records[] = [
                'u_id' => $userId,
                'p_id' => $providerId,
                'stream_id' => $stream['id'],
                'other_id' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ];
        }

        if (!empty($records)) {
            $this->db->insert_many(
                table: 'stream_missing',
                data: $records,
                ignore_duplicates: true
            );
        }

    }
}