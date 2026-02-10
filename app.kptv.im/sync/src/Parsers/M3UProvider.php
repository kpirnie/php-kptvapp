<?php
declare(strict_types=1);

namespace Kptv\IptvSync\Parsers;

class M3UProvider extends BaseProvider
{
    public function fetchStreams(): array
    {
        $response = $this->makeRequest($this->domain);
        $content = $response->getBody()->getContents();
        $streams = $this->parseM3u($content);
        return $streams;
    }

    private function parseM3u(string $content): array
    {
        $streams = [];
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = explode("\n", $content);
        $i = 0;

        while ($i < count($lines)) {
            $line = trim($lines[$i]);

            if (str_starts_with($line, '#EXTINF:')) {
                $info = $this->parseExtinfLine($line);

                $i++;
                $url = '';
                
                while ($i < count($lines)) {
                    $nextLine = trim($lines[$i]);
                    if ($nextLine !== '' && !str_starts_with($nextLine, '#')) {
                        $url = $nextLine;
                        break;
                    }
                    $i++;
                }

                if ($url !== '' && $info['name'] !== '') {
                    $streamType = $this->determineStreamType($url, $info);
                    // we want to skip VOD
                    if($streamType === 4){
                        continue;
                    }
                    $streams[] = [
                        's_type_id' => $streamType,
                        's_orig_name' => $info['name'],
                        's_stream_uri' => $url,
                        's_tvg_id' => $info['tvg_id'],
                        's_tvg_group' => $info['group'],
                        's_tvg_logo' => $info['logo'],
                        's_extras' => null
                    ];
                }
            }
            $i++;
        }

        return $streams;
    }

    private function parseExtinfLine(string $line): array
    {
        $info = ['name' => '', 'tvg_id' => null, 'logo' => null, 'group' => null];

        if (preg_match('/tvg-id="([^"]*)"/', $line, $m)) {
            $info['tvg_id'] = $m[1] ?: null;
        }

        if (preg_match('/tvg-name="([^"]*)"/', $line, $m)) {
            $info['name'] = trim($m[1]);
        }

        if (preg_match('/tvg-logo="([^"]*)"/', $line, $m)) {
            $info['logo'] = $m[1] ?: null;
        }

        if (preg_match('/group-title="([^"]*)"/', $line, $m)) {
            $info['group'] = $m[1] ?: null;
        }

        if (empty($info['name']) && preg_match('/,\s*(.+?)\s*$/', $line, $m)) {
            $info['name'] = trim($m[1]);
        }

        return $info;
    }

    private function determineStreamType(string $url, array $info): int
    {
        $groupLower = strtolower($info['group'] ?? '');
        $urlLower = strtolower($url);
        
        // Check URL patterns for VOD/movie
        if (str_contains($urlLower, '/movie/') || str_contains($urlLower, '/vod/')) {
            return 4; // Will be skipped
        }

        if (str_contains($groupLower, 'vod') || str_contains($groupLower, 'movie')) {
            return 4; // Will be skipped
        }
        
        if (str_contains($groupLower, 'series') || str_contains(strtolower($info['name']), '24/7')) {
            return 5;
        }
        
        return 0; // Live
    }
}