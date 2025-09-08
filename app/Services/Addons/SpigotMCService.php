<?php

namespace Pterodactyl\Services\Addons;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Storage;
use Pterodactyl\Models\Server;

class SpigotMCService implements AddonServiceInterface
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.spiget.org/v2/',
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Pterodactyl Panel (contact@pterodactyl.io)',
            ],
        ]);
    }

    public function search(string $query, string $gameVersion = '', string $category = '', int $page = 1): array
    {
        try {
            $params = [
                'size' => 20,
                'page' => $page - 1,
                'sort' => '-downloads',
            ];

            // SpigotMC doesn't have a direct search endpoint, so we'll get resources and filter
            $response = $this->client->get('resources', [
                'query' => $params,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            // Filter by query if provided
            if ($query) {
                $data = array_filter($data, function ($item) use ($query) {
                    return stripos($item['name'], $query) !== false || 
                           stripos($item['tag'] ?? '', $query) !== false;
                });
                $data = array_slice($data, 0, 20); // Limit to 20 results
            }

            return [
                'data' => array_map([$this, 'formatAddon'], $data ?? []),
                'pagination' => [
                    'page' => $page,
                    'totalCount' => count($data), // Approximate since we don't have total count
                ],
            ];
        } catch (RequestException $e) {
            return ['data' => [], 'pagination' => ['page' => $page, 'totalCount' => 0]];
        }
    }

    public function getAddon(string $addonId): array
    {
        try {
            $response = $this->client->get("resources/{$addonId}");

            $data = json_decode($response->getBody()->getContents(), true);

            return $this->formatAddon($data);
        } catch (RequestException $e) {
            return [];
        }
    }

    public function getVersions(string $addonId, string $gameVersion = ''): array
    {
        try {
            $response = $this->client->get("resources/{$addonId}/versions");

            $data = json_decode($response->getBody()->getContents(), true);

            return array_map([$this, 'formatVersion'], $data ?? []);
        } catch (RequestException $e) {
            return [];
        }
    }

    public function downloadAddon(string $serverUuid, string $addonId, string $versionId): array
    {
        try {
            $server = Server::where('uuid', $serverUuid)->firstOrFail();
            
            // Get resource info
            $resourceResponse = $this->client->get("resources/{$addonId}");
            $resourceData = json_decode($resourceResponse->getBody()->getContents(), true);

            // Get version info
            $versionResponse = $this->client->get("resources/{$addonId}/versions/{$versionId}");
            $versionData = json_decode($versionResponse->getBody()->getContents(), true);

            // SpigotMC requires external downloads, so we can't directly download
            // We'll provide the download URL instead
            $downloadUrl = "https://www.spigotmc.org/resources/{$addonId}/download?version={$versionId}";
            
            return [
                'success' => true,
                'requiresManualDownload' => true,
                'downloadUrl' => $downloadUrl,
                'fileName' => $resourceData['name'] . '.jar',
                'message' => 'SpigotMC resources require manual download. Please download from the provided URL.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getSupportedVersions(): array
    {
        // SpigotMC doesn't provide a versions API endpoint
        // Return common Minecraft versions
        return [
            ['id' => '1.21', 'name' => '1.21', 'type' => 'release'],
            ['id' => '1.20.6', 'name' => '1.20.6', 'type' => 'release'],
            ['id' => '1.20.4', 'name' => '1.20.4', 'type' => 'release'],
            ['id' => '1.20.1', 'name' => '1.20.1', 'type' => 'release'],
            ['id' => '1.19.4', 'name' => '1.19.4', 'type' => 'release'],
            ['id' => '1.18.2', 'name' => '1.18.2', 'type' => 'release'],
            ['id' => '1.17.1', 'name' => '1.17.1', 'type' => 'release'],
            ['id' => '1.16.5', 'name' => '1.16.5', 'type' => 'release'],
        ];
    }

    public function getPlatformName(): string
    {
        return 'SpigotMC';
    }

    private function formatAddon(array $addon): array
    {
        return [
            'id' => $addon['id'],
            'name' => $addon['name'],
            'description' => $addon['tag'] ?? '',
            'downloadCount' => $addon['downloads'] ?? 0,
            'iconUrl' => isset($addon['icon']['url']) ? "https://www.spigotmc.org/{$addon['icon']['url']}" : '',
            'author' => $addon['author']['name'] ?? 'Unknown',
            'platform' => 'spigotmc',
            'categories' => [$addon['category']['name'] ?? 'Plugin'],
            'gameVersions' => $addon['testedVersions'] ?? [],
        ];
    }

    private function formatVersion(array $version): array
    {
        return [
            'id' => $version['id'],
            'name' => $version['name'],
            'fileName' => '',
            'releaseType' => 'release',
            'gameVersions' => [],
            'downloadUrl' => '',
            'fileDate' => '',
            'fileLength' => 0,
        ];
    }
}