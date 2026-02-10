<?php

declare(strict_types=1);

namespace Kptv\IptvSync\Parsers;

class XtremeCodesProvider extends BaseProvider
{
    private string $apiLive;
    private string $apiSeries;
    //private string $apiVod;
    private string $streamLive;
    private string $streamSeries;
    //private string $streamVod;

    public function __construct(array $provider)
    {
        parent::__construct($provider);

        $this->apiLive = "{$this->domain}/player_api.php?username={$this->username}&password={$this->password}&action=get_live_streams";
        $this->apiSeries = "{$this->domain}/player_api.php?username={$this->username}&password={$this->password}&action=get_series";
        //$this->apiVod = "{$this->domain}/player_api.php?username={$this->username}&password={$this->password}&action=get_vod_streams";

        $this->streamLive = "{$this->domain}/live/{$this->username}/{$this->password}/%s.{$this->streamTypeExt}";
        $this->streamSeries = "{$this->domain}/series/{$this->username}/{$this->password}/%s.{$this->streamTypeExt}";
        //this->streamVod = "{$this->domain}/movie/{$this->username}/{$this->password}/%s.{$this->streamTypeExt}";
    }

    public function fetchStreams(): array
    {
        echo "Fetching streams from Xtreme Codes API...\n";
        $allStreams = [];

        // Fetch Live Streams
        try {
            echo "Fetching live streams...\n";
            $liveStreams = $this->fetchApi($this->apiLive, 0);
            echo sprintf("Retrieved %s live streams\n", number_format(count($liveStreams)));
            $allStreams = [...$allStreams, ...$liveStreams];
            sleep(1);
        } catch (\Exception $e) {
            echo "⚠️  Error fetching live streams: {$e->getMessage()}\n";
        }

        // Fetch VOD Streams
        /*try {
            echo "Fetching VOD streams...\n";
            $vodStreams = $this->fetchApi($this->apiVod, 4);
            echo sprintf("Retrieved %s VOD streams\n", number_format(count($vodStreams)));
            $allStreams = [...$allStreams, ...$vodStreams];
            sleep(1);
        } catch (\Exception $e) {
            echo "⚠️  Error fetching VOD streams: {$e->getMessage()}\n";
        }*/

        // Fetch Series
        try {
            echo "Fetching series...\n";
            $seriesStreams = $this->fetchApi($this->apiSeries, 5);
            echo sprintf("Retrieved %s series\n", number_format(count($seriesStreams)));
            $allStreams = [...$allStreams, ...$seriesStreams];
        } catch (\Exception $e) {
            echo "⚠️  Error fetching series: {$e->getMessage()}\n";
        }

        echo sprintf("Total streams retrieved: %s\n", number_format(count($allStreams)));
        return $allStreams;
    }

    private function fetchApi(string $url, int $streamType): array
    {
        if ($streamType === 4) {
            return [];
        }

        $response = $this->makeRequest($url);
        $data = json_decode($response->getBody()->getContents(), true);

        if (!is_array($data)) {
            throw new \RuntimeException('Invalid API response - expected array');
        }

        $streams = [];
        $skipped = 0;

        foreach ($data as $item) {

            $itemType = $item['stream_type'] ?? null;
            if ($itemType === 'movie' || $itemType === 4 || $itemType === '4') {
                continue;
            }
            $catName = strtolower($item['category_name'] ?? '');
            if (str_contains($catName, 'vod') || str_contains($catName, 'movie') || str_contains($catName, 'film')) {
                continue;
            }

            $streamId = $item['stream_id'] ?? $item['series_id'] ?? null;

            if ($streamId === null) {
                $skipped++;
                continue;
            }

            $uri = match ($streamType) {
                0 => sprintf($this->streamLive, $streamId),
                //4 => sprintf($this->streamVod, $streamId),
                5 => sprintf($this->streamSeries, $streamId),
                default => ''
            };

            if (empty($uri)) {
                $skipped++;
                continue;
            }

            $name = $item['name'] ?? '';
            $typeId = $streamType;
            if (str_contains(strtolower($name), '24/7')) {
                $typeId = 5;
            }

            $streams[] = [
                's_type_id' => $typeId,
                's_orig_name' => $item['name'] ?? '',
                's_stream_uri' => $uri,
                's_tvg_id' => $item['epg_channel_id'] ?? $item['tmdb_id'] ?? null,
                's_tvg_group' => $item['category_name'] ?? null,
                's_tvg_logo' => $item['stream_icon'] ?? $item['cover'] ?? null,
                's_extras' => null
            ];
        }

        if ($skipped > 0) {
            echo sprintf("  Skipped %s items (missing stream_id)\n", number_format($skipped));
        }

        return $streams;
    }
}
