<?php

declare(strict_types=1);

namespace Kptv\IptvSync\Parsers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

abstract class BaseProvider
{
    protected Client $client;
    protected string $domain;
    protected ?string $username;
    protected ?string $password;
    protected string $streamTypeExt;
    protected int $maxRetries = 3;
    protected int $retryDelay = 2;

    public function __construct(
        protected readonly array $provider
    ) {
        $this->domain = $provider['sp_domain'];
        $this->username = $provider['sp_username'] ?? null;
        $this->password = $provider['sp_password'] ?? null;
        $this->streamTypeExt = $provider['sp_stream_type'] === 0 ? 'ts' : 'm3u8';

        $this->client = new Client([
            'timeout' => 60,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => '*/*',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive'
            ]
        ]);
    }

    abstract public function fetchStreams(): array;

    protected function makeRequest(string $url, int $timeout = 60): ResponseInterface
    {
        for ($attempt = 0; $attempt < $this->maxRetries; $attempt++) {
            try {
                return $this->client->get($url, ['timeout' => $timeout]);
            } catch (ConnectException $e) {
                if ($attempt < $this->maxRetries - 1) {
                    $delay = $this->retryDelay * ($attempt + 1);
                    sleep($delay);
                } else {
                    throw $e;
                }
            } catch (RequestException $e) {
                if ($e->getCode() === CURLE_OPERATION_TIMEDOUT && $attempt < $this->maxRetries - 1) {
                    sleep($this->retryDelay);
                } else {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Failed to make request after all retries');
    }
}
