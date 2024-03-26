<?php

namespace Fleetbase\RegistryBridge\Support;

use Illuminate\Support\Facades\Http;

class Bridge
{
    /**
     * Creates a complete URL by concatenating the base URL from the configuration with the provided URI.
     *
     * This method ensures that the base URL from the configuration has a trailing slash before
     * concatenating it with the provided URI. It handles URL formation for making HTTP requests.
     *
     * @param string $uri the specific URI to append to the base URL
     *
     * @return string the complete URL formed by concatenating the base URL and the provided URI
     */
    private static function createUrl(string $uri = ''): string
    {
        $url = config('registry-bridge.registry.host');

        return rtrim($url, '/') . '/' . $uri;
    }

    /**
     * Sends a GET request to the specified URI.
     *
     * @param string $uri        URI to send the GET request to
     * @param array  $parameters parameters to include in the request
     * @param array  $options    additional options for the HTTP client
     *
     * @return \Illuminate\Http\Client\Response
     */
    public static function get($uri, $parameters = [], $options = [])
    {
        return Http::withOptions($options)->get(static::createUrl($uri), $parameters);
    }

    /**
     * Sends a POST request to the specified URI.
     *
     * @param string $uri        URI to send the GET request to
     * @param array  $parameters parameters to include in the request
     * @param array  $options    additional options for the HTTP client
     *
     * @return \Illuminate\Http\Client\Response
     */
    public static function post($uri, $parameters = [], $options = [])
    {
        return Http::withOptions($options)->post(static::createUrl($uri), $parameters);
    }
}
