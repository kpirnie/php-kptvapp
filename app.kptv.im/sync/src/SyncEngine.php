<?php

declare(strict_types=1);

namespace Kptv\IptvSync;

use Kptv\IptvSync\KpDb;
use Kptv\IptvSync\Database\WhereClause;
use Kptv\IptvSync\Database\ComparisonOperator;
use Kptv\IptvSync\Parsers\ProviderFactory;

class SyncEngine
{
    private FilterManager $filterManager;

    public function __construct(
        private readonly KpDb $db,
        array $ignoreFields = []
    ) {
        $this->filterManager = new FilterManager($db);
    }

    public function syncProvider(array $provider): int
    {
        $providerId = $provider['id'];
        $userId = $provider['u_id'];
        $shouldFilter = $provider['sp_should_filter'] === 1;

        echo sprintf("Provider: %s (ID: %d)\n", $provider['sp_name'], $providerId);

        // Get provider parser
        $parser = ProviderFactory::create($provider);

        // Fetch streams from provider
        echo "Fetching streams from provider...\n";
        $rawStreams = $parser->fetchStreams();
        echo sprintf("Retrieved %s streams from provider\n", number_format(count($rawStreams)));

        // Apply filters if needed
        if ($shouldFilter) {
            echo "Applying filters...\n";
            $filters = $this->filterManager->getFilters($userId);
            echo sprintf("Found %s active filters for user %d\n", number_format(count($filters)), $userId);

            if (!empty($filters)) {
                $beforeCount = count($rawStreams);
                $rawStreams = $this->filterManager->applyFilters($rawStreams, $filters);
                $afterCount = count($rawStreams);
                $filtered = $beforeCount - $afterCount;

                echo sprintf(
                    "Filter results: %s streams kept, %s filtered out (%.1f%% filtered)\n",
                    number_format($afterCount),
                    number_format($filtered),
                    ($beforeCount > 0 ? ($filtered / $beforeCount * 100) : 0)
                );
            } else {
                echo "No filters configured - all streams will be processed\n";
            }
        } else {
            echo "Filtering disabled for this provider\n";
        }

        if (empty($rawStreams)) {
            echo "No streams to sync after filtering\n";
            return 0;
        }

        // Clear temp table for this provider
        echo "Clearing temporary table...\n";
        $this->db->delete(
            table: 'stream_temp',
            where: [
                new WhereClause('u_id', $userId, ComparisonOperator::EQ),
                new WhereClause('p_id', $providerId, ComparisonOperator::EQ)
            ]
        );

        // Insert into temp table
        if (!empty($rawStreams)) {
            echo "Inserting streams into temporary table...\n";
            $tempRecords = [];
            foreach ($rawStreams as $stream) {
                $tempRecords[] = [
                    'u_id' => $userId,
                    'p_id' => $providerId,
                    's_type_id' => $stream['s_type_id'],
                    's_orig_name' => $stream['s_orig_name'],
                    's_stream_uri' => $stream['s_stream_uri'],
                    's_tvg_id' => $stream['s_tvg_id'] ?? null,
                    's_tvg_logo' => $stream['s_tvg_logo'] ?? null,
                    's_extras' => $stream['s_extras'] ?? null,
                    's_group' => $stream['s_tvg_group'] ?? null
                ];
            }

            // Insert in batches
            $this->db->insert_many(
                table: 'stream_temp',
                data: $tempRecords,
                ignore_duplicates: true,
                batch_size: 1000
            );
            echo sprintf("Inserted %s records into temporary table\n", number_format(count($tempRecords)));
        }

        // Sync from temp to main streams table
        echo "Syncing to main streams table...\n";
        $syncedCount = $this->syncTempToStreams($userId, $providerId);

        echo sprintf("Sync complete: %s streams processed\n", number_format($syncedCount));
        return $syncedCount;
    }

    private function syncTempToStreams(int $userId, int $providerId): int
    {
        $existingStreams = $this->db->get_all(
            table: 'streams',
            columns: ['id', 's_orig_name', 's_stream_uri'],
            where: [
                new WhereClause('u_id', $userId, ComparisonOperator::EQ),
                new WhereClause('p_id', $providerId, ComparisonOperator::EQ)
            ]
        );

        // Build lookups by name and by uri separately
        $existingByName = [];
        $existingByUri = [];
        foreach ($existingStreams ?? [] as $s) {
            $nameKey = strtolower($s['s_orig_name']);
            $existingByName[$nameKey] ??= $s;
            $existingByUri[$s['s_stream_uri']] ??= $s;
        }

        $tempStreams = $this->db->get_all(
            table: 'stream_temp',
            where: [
                new WhereClause('u_id', $userId, ComparisonOperator::EQ),
                new WhereClause('p_id', $providerId, ComparisonOperator::EQ)
            ]
        );

        $tempStreams = array_filter($tempStreams, fn($s) => (int)($s['s_type_id'] ?? 0) !== 4);

        if (empty($tempStreams)) {
            echo "No streams in temporary table\n";
            return 0;
        }

        $uriUpdates = [];
        $nameUpdates = [];
        $inserts = [];
        $unchanged = 0;
        $processed = []; // Track processed stream IDs to avoid double-processing

        foreach ($tempStreams as $temp) {
            $nameKey = strtolower($temp['s_orig_name']);
            $tempUri = $temp['s_stream_uri'];

            // Check 1: Does s_orig_name exist?
            if (isset($existingByName[$nameKey])) {
                $existing = $existingByName[$nameKey];

                // Skip if already processed
                if (isset($processed[$existing['id']])) {
                    continue;
                }

                // Update s_stream_uri ONLY if different
                if ($existing['s_stream_uri'] !== $tempUri) {
                    $uriUpdates[] = [$existing['id'], $tempUri];
                } else {
                    $unchanged++;
                }

                $processed[$existing['id']] = true;
                continue;
            }

            // Check 2: Does s_stream_uri exist?
            if (isset($existingByUri[$tempUri])) {
                $existing = $existingByUri[$tempUri];

                // Skip if already processed
                if (isset($processed[$existing['id']])) {
                    continue;
                }

                // Update s_orig_name ONLY if different
                if (strtolower($existing['s_orig_name']) !== $nameKey) {
                    $nameUpdates[] = [$existing['id'], $temp['s_orig_name']];
                } else {
                    $unchanged++;
                }

                $processed[$existing['id']] = true;
                continue;
            }

            // Neither exist - insert as new
            $inserts[] = $temp;
        }

        echo sprintf(
            "Analysis: %s URI updates, %s name updates, %s to insert, %s unchanged\n",
            number_format(count($uriUpdates)),
            number_format(count($nameUpdates)),
            number_format(count($inserts)),
            number_format($unchanged)
        );

        // Batch URI updates
        if (!empty($uriUpdates)) {
            echo "Updating stream URIs...\n";
            $updateCount = 0;
            foreach ($uriUpdates as [$streamId, $uri]) {
                $this->db->update(
                    table: 'streams',
                    where: [new WhereClause('id', $streamId, ComparisonOperator::EQ)],
                    data: ['s_stream_uri' => $uri, 's_updated' => null]
                );
                $updateCount++;
                if ($updateCount % 500 === 0) {
                    echo sprintf("  Updated %s streams...\n", number_format($updateCount));
                }
            }
            echo sprintf("Updated %s stream URIs\n", number_format(count($uriUpdates)));
        }

        // Batch name updates
        if (!empty($nameUpdates)) {
            echo "Updating stream names...\n";
            $updateCount = 0;
            foreach ($nameUpdates as [$streamId, $name]) {
                $this->db->update(
                    table: 'streams',
                    where: [new WhereClause('id', $streamId, ComparisonOperator::EQ)],
                    data: ['s_orig_name' => $name, 's_updated' => null]
                );
                $updateCount++;
                if ($updateCount % 500 === 0) {
                    echo sprintf("  Updated %s streams...\n", number_format($updateCount));
                }
            }
            echo sprintf("Updated %s stream names\n", number_format(count($nameUpdates)));
        }

        // Batch inserts - full data for new streams
        if (!empty($inserts)) {
            echo "Inserting new streams...\n";
            $insertRecords = [];
            foreach ($inserts as $data) {
                // Skip VOD streams
                if ((int)$data['s_type_id'] === 4) {
                    continue;
                }
                $insertRecords[] = [
                    'u_id' => $userId,
                    'p_id' => $providerId,
                    's_type_id' => $data['s_type_id'],
                    's_active' => 0,
                    's_channel' => '0',
                    's_name' => $data['s_orig_name'],
                    's_orig_name' => $data['s_orig_name'],
                    's_stream_uri' => $data['s_stream_uri'],
                    's_tvg_id' => $data['s_tvg_id'],
                    's_tvg_logo' => $data['s_tvg_logo'],
                    's_tvg_group' => $data['s_group'],
                    's_extras' => $data['s_extras']
                ];
            }

            $this->db->insert_many(
                table: 'streams',
                data: $insertRecords,
                ignore_duplicates: true,
                batch_size: 500
            );
            echo sprintf("Inserted %s new streams\n", number_format(count($inserts)));
        }

        // Clear temp table after sync
        echo "Cleaning up temporary table...\n";
        $this->db->delete(
            table: 'stream_temp',
            where: [
                new WhereClause('u_id', $userId, ComparisonOperator::EQ),
                new WhereClause('p_id', $providerId, ComparisonOperator::EQ)
            ]
        );

        return count($uriUpdates) + count($nameUpdates) + count($inserts);
    }
}
