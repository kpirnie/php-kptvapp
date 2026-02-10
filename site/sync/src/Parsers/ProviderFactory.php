<?php

declare(strict_types=1);

namespace Kptv\IptvSync\Parsers;

use InvalidArgumentException;

class ProviderFactory
{
    public static function create(array $provider): BaseProvider
    {
        $spType = $provider['sp_type'];

        return match ($spType) {
            0 => new XtremeCodesProvider($provider),
            1 => new M3UProvider($provider),
            default => throw new InvalidArgumentException("Unknown provider type: {$spType}")
        };
    }
}
