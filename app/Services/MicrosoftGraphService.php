<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MicrosoftGraphService
{
    private const GRAPH_URL = 'https://graph.microsoft.com/v1.0';

    /**
     * Obtain an application access token.
     */
    public function getAccessToken(): string
    {
        return Cache::remember(
            'microsoft_graph_access_token',
            now()->addMinutes(50),
            function (): string {
                $tenantId = config('services.microsoft_graph.tenant_id');
                $clientId = config('services.microsoft_graph.client_id');
                $clientSecret = config('services.microsoft_graph.client_secret');

                if (! $tenantId || ! $clientId || ! $clientSecret) {
                    throw new RuntimeException(
                        'Microsoft Graph credentials are not configured.'
                    );
                }

                $response = Http::asForm()
                    ->acceptJson()
                    ->timeout(20)
                    ->post(
                        "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
                        [
                            'client_id' => $clientId,
                            'client_secret' => $clientSecret,
                            'scope' => 'https://graph.microsoft.com/.default',
                            'grant_type' => 'client_credentials',
                        ]
                    );

                $response->throw();

                $token = $response->json('access_token');

                if (! is_string($token) || $token === '') {
                    throw new RuntimeException(
                        'Microsoft Entra ID did not return an access token.'
                    );
                }

                return $token;
            }
        );
    }

    /**
     * Make an authenticated Microsoft Graph GET request.
     *
     * Supports relative Graph endpoints and absolute Microsoft Graph
     * pagination URLs.
     */
    public function get(string $endpoint): array
    {
        $url = $this->resolveGraphUrl($endpoint);

        $response = Http::withToken($this->getAccessToken())
            ->acceptJson()
            ->timeout(30)
            ->get($url);

        // Refresh an expired or revoked cached token and retry once.
        if ($response->status() === 401) {
            Cache::forget('microsoft_graph_access_token');

            $response = Http::withToken($this->getAccessToken())
                ->acceptJson()
                ->timeout(30)
                ->get($url);
        }

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * Retrieve every page returned by a Microsoft Graph collection.
     *
     * Returns a flat array containing items from every page.
     */
    public function getAllPages(string $endpoint): array
    {
        $items = [];
        $nextUrl = $this->resolveGraphUrl($endpoint);
        $visitedUrls = [];

        while ($nextUrl !== null) {
            if (isset($visitedUrls[$nextUrl])) {
                throw new RuntimeException(
                    'Microsoft Graph pagination returned a repeated URL.'
                );
            }

            $visitedUrls[$nextUrl] = true;

            $page = $this->get($nextUrl);
            $pageItems = $page['value'] ?? null;

            if (! is_array($pageItems)) {
                throw new RuntimeException(
                    'Microsoft Graph pagination expected a collection with a value array.'
                );
            }

            foreach ($pageItems as $item) {
                $items[] = $item;
            }

            $nextLink = $page['@odata.nextLink'] ?? null;

            if ($nextLink === null || $nextLink === '') {
                $nextUrl = null;
                continue;
            }

            if (! is_string($nextLink)) {
                throw new RuntimeException(
                    'Microsoft Graph returned an invalid pagination URL.'
                );
            }

            $nextUrl = $this->resolveGraphUrl($nextLink);
        }

        return $items;
    }

    /**
     * Iterate through a Microsoft Graph collection one page at a time.
     *
     * Yields each page's value array without accumulating all items
     * in memory.
     */
    public function iteratePages(string $endpoint): \Generator
    {
        $nextUrl = $this->resolveGraphUrl($endpoint);
        $visitedUrls = [];

        while ($nextUrl !== null) {
            if (isset($visitedUrls[$nextUrl])) {
                throw new RuntimeException(
                    'Microsoft Graph pagination returned a repeated URL.'
                );
            }

            $visitedUrls[$nextUrl] = true;

            $page = $this->get($nextUrl);
            $pageItems = $page['value'] ?? null;

            if (! is_array($pageItems)) {
                throw new RuntimeException(
                    'Microsoft Graph pagination expected a collection with a value array.'
                );
            }

            yield $pageItems;

            $nextLink = $page['@odata.nextLink'] ?? null;

            if ($nextLink === null || $nextLink === '') {
                $nextUrl = null;
                continue;
            }

            if (! is_string($nextLink)) {
                throw new RuntimeException(
                    'Microsoft Graph returned an invalid pagination URL.'
                );
            }

            $nextUrl = $this->resolveGraphUrl($nextLink);
        }
    }

    /**
     * Download a file from a SharePoint drive.
     *
     * Microsoft Graph normally responds to /content with a redirect
     * to a temporary download URL. The temporary URL is validated
     * before being requested, and the Graph bearer token is not
     * forwarded to that URL.
     *
     * Returns the original file bytes, not extracted text.
     */

    public function downloadFile(string $driveId, string $itemId): string
    {
        if (trim($driveId) === '' || trim($itemId) === '') {
            throw new RuntimeException(
                'A SharePoint drive ID and item ID are required.'
            );
        }

        $startedAt = microtime(true);

        $endpoint = 'drives/' . rawurlencode($driveId)
            . '/items/' . rawurlencode($itemId)
            . '/content';

        $url = $this->resolveGraphUrl($endpoint);

        Log::info('SharePoint download: Graph request started', [
            'item_id' => $itemId,
        ]);

        $response = Http::withToken($this->getAccessToken())
            ->timeout(60)
            ->withOptions([
                'allow_redirects' => false,
            ])
            ->get($url);

        if ($response->status() === 401) {
            Cache::forget('microsoft_graph_access_token');

            Log::info('SharePoint download: refreshing access token');

            $response = Http::withToken($this->getAccessToken())
                ->timeout(60)
                ->withOptions([
                    'allow_redirects' => false,
                ])
                ->get($url);
        }

        Log::info('SharePoint download: Graph response received', [
            'item_id' => $itemId,
            'http_status' => $response->status(),
            'duration_ms' => (int) round(
                (microtime(true) - $startedAt) * 1000
            ),
        ]);

        if (
            $response->status() >= 300
            && $response->status() < 400
        ) {
            $downloadUrl = $response->header('Location');
            $parts = is_string($downloadUrl)
                ? parse_url($downloadUrl)
                : false;

            if (
                ! is_string($downloadUrl)
                || ! is_array($parts)
                || strtolower($parts['scheme'] ?? '') !== 'https'
                || empty($parts['host'])
                || isset($parts['user'])
                || isset($parts['pass'])
                || isset($parts['port'])
            ) {
                throw new RuntimeException(
                    'Microsoft Graph returned an invalid file download URL.'
                );
            }

            Log::info('SharePoint download: temporary download started', [
                'item_id' => $itemId,
                'elapsed_ms' => (int) round(
                    (microtime(true) - $startedAt) * 1000
                ),
            ]);

            // The temporary URL has its own authorization.
            // Never forward the Graph bearer token to this URL.
            $response = Http::timeout(120)
                ->withOptions([
                    'allow_redirects' => false,
                ])
                ->get($downloadUrl);

            Log::info('SharePoint download: temporary download completed', [
                'item_id' => $itemId,
                'http_status' => $response->status(),
                'downloaded_bytes' => strlen($response->body()),
                'duration_ms' => (int) round(
                    (microtime(true) - $startedAt) * 1000
                ),
            ]);
        }

        $response->throw();

        $content = $response->body();

        Log::info('SharePoint download: finished', [
            'item_id' => $itemId,
            'downloaded_bytes' => strlen($content),
            'duration_ms' => (int) round(
                (microtime(true) - $startedAt) * 1000
            ),
        ]);

        return $content;
    }


    /**
     * Resolve a relative endpoint or validate an absolute Graph URL.
     */
    private function resolveGraphUrl(string $endpoint): string
    {
        $endpoint = trim($endpoint);

        if ($endpoint === '') {
            throw new RuntimeException(
                'Microsoft Graph endpoint cannot be empty.'
            );
        }

        if (str_starts_with($endpoint, '/')) {
            $endpoint = ltrim($endpoint, '/');
        }

        if (preg_match('/^https?:\/\//i', $endpoint)) {
            $parts = parse_url($endpoint);
            $expectedHost = parse_url(self::GRAPH_URL, PHP_URL_HOST);

            if (
                ! is_array($parts)
                || strtolower($parts['scheme'] ?? '') !== 'https'
                || strtolower($parts['host'] ?? '') !== $expectedHost
                || isset($parts['user'])
                || isset($parts['pass'])
                || isset($parts['port'])
            ) {
                throw new RuntimeException(
                    'Pagination URLs must belong to the official Microsoft Graph HTTPS endpoint.'
                );
            }

            return $endpoint;
        }

        return self::GRAPH_URL . '/' . $endpoint;
    }

    /**
     * Resolve the HR-GARII SharePoint site.
     */
    public function getHrGariiSite(): array
    {
        $configuredSiteId = config('services.microsoft_graph.site_id');

        if ($configuredSiteId) {
            return $this->get(
                'sites/' . rawurlencode($configuredSiteId)
            );
        }

        return $this->get(
            'sites/rapidindonesia.sharepoint.com:/sites/HR-GARII'
        );
    }
}
