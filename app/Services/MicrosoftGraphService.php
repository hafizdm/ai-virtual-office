<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
     */
    public function get(string $endpoint): array
    {
        $endpoint = ltrim($endpoint, '/');

        $response = Http::withToken($this->getAccessToken())
            ->acceptJson()
            ->timeout(30)
            ->get(self::GRAPH_URL . '/' . $endpoint);

        // If a cached token has expired or been revoked, refresh once.
        if ($response->status() === 401) {
            Cache::forget('microsoft_graph_access_token');

            $response = Http::withToken($this->getAccessToken())
                ->acceptJson()
                ->timeout(30)
                ->get(self::GRAPH_URL . '/' . $endpoint);
        }

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * Resolve the SharePoint site from its hostname and server-relative path.
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
