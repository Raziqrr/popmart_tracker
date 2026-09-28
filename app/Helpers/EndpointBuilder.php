<?php

namespace App\Helpers;

use InvalidArgumentException;

class EndpointBuilder
{
    public function __construct(protected string $client)
    {
        if (! is_string(config("apis.{$client}.base_url"))) {
            throw new InvalidArgumentException("No base_url configured for API client [{$client}].");
        }
    }

    /**
     * @param  array<int, string>  $segments  path parts, joined with '/' in order (e.g. ['rpc', 'ec', 'spu/public_FindByIds'])
     */
    public function build(array $segments, array $query = []): string
    {
        $path = collect($segments)
            ->filter(fn ($segment) => $segment !== '')
            ->map(fn ($segment) => trim($segment, '/'))
            ->implode('/');

        $url = rtrim(config("apis.{$this->client}.base_url"), '/').'/'.$path;

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }
}
