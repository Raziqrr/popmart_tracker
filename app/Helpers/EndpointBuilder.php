<?php

namespace App\Helpers;

use InvalidArgumentException;

class EndpointBuilder
{
    public function __construct(protected string $client)
    {
        $baseUrl = app(ConfigBuilder::class)->get("apis.{$client}.base_url");

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new InvalidArgumentException("No base_url configured for API client [{$client}].");
        }
    }

    /**
     * @param  array<int, string>  $segments  path parts, joined with '/' in order (e.g. ['rpc', 'ec', 'spu/public_FindByIds'])
     */
    public function build(array $segments, array $query = []): string
    {
        $baseUrl = (string) app(ConfigBuilder::class)->get("apis.{$this->client}.base_url");

        $path = collect($segments)
            ->filter(fn ($segment) => $segment !== '')
            ->map(fn ($segment) => trim($segment, '/'))
            ->implode('/');

        $url = rtrim($baseUrl, '/').'/'.$path;

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }
}
