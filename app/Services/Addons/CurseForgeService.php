<?php

namespace Pterodactyl\Services\Addons;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Storage;
use Pterodactyl\Models\Server;

class CurseForgeService implements AddonServiceInterface
{
    private Client $client;
    private string $apiKey;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.curseforge.com/v1/',
            'timeout' => 30,
        ]);
        $this->apiKey = config('services.curseforge.api_key', '') ?? '';
    }

    public function search(string $query, string $gameVersion = '', string $category = '', int $page = 1): array
    {
        try {
            $params = [
                'gameId' => 432, // Minecraft
                'searchFilter' => $query,
                'pageSize' => 20,
                'index' => ($page - 1) * 20,
            ];

            if ($gameVersion) {
                $params['gameVersion'] = $gameVersion;
            }

            if ($category) {
                $params['categoryId'] = $this->getCategoryId($category);
            }

            $response = $this->client->get('mods/search', [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                ],
                'query' => $params,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'data' => array_map([$this, 'formatAddon'], $data['data'] ?? []),
                'pagination' => [
                    'page' => $page,
                    'totalCount' => $data['pagination']['totalCount'] ?? 0,
                ],
            ];
        } catch (RequestException $e) {
            return ['data' => [], 'pagination' => ['page' => $page, 'totalCount' => 0]];
        }
    }

    public function getAddon(string $addonId): array
    {
        try {
            $response = $this->client->get("mods/{$addonId}", [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $this->formatAddon($data['data']);
        } catch (RequestException $e) {
            return [];
        }
    }

    public function getVersions(string $addonId, string $gameVersion = ''): array
    {
        try {
            $params = ['pageSize' => 50];
            if ($gameVersion) {
                $params['gameVersion'] = $gameVersion;
            }

            $response = $this->client->get("mods/{$addonId}/files", [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                ],
                'query' => $params,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return array_map([$this, 'formatVersion'], $data['data'] ?? []);
        } catch (RequestException $e) {
            return [];
        }
    }

    public function downloadAddon(string $serverUuid, string $addonId, string $versionId): array
    {
        try {
            $server = Server::where('uuid', $serverUuid)->firstOrFail();
            
            // Get download URL
            $response = $this->client->get("mods/{$addonId}/files/{$versionId}/download-url", [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                ],
            ]);

            $downloadUrl = json_decode($response->getBody()->getContents(), true)['data'];

            // Get file info
            $fileResponse = $this->client->get("mods/{$addonId}/files/{$versionId}", [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                ],
            ]);

            $fileData = json_decode($fileResponse->getBody()->getContents(), true)['data'];
            $fileName = $fileData['fileName'];

            // Download file
            $fileContent = file_get_contents($downloadUrl);
            
            // Determine target directory based on file type
            $targetDir = str_ends_with($fileName, '.jar') ? 'plugins' : 'mods';
            $targetPath = "servers/{$server->uuid}/{$targetDir}/{$fileName}";

            Storage::disk('local')->put($targetPath, $fileContent);

            return [
                'success' => true,
                'fileName' => $fileName,
                'path' => $targetPath,
                'size' => strlen($fileContent),
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
        try {
            $response = $this->client->get('minecraft/version', [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return array_map(function ($version) {
                return [
                    'id' => $version['id'],
                    'name' => $version['versionString'],
                    'type' => $version['versionTypeId'],
                ];
            }, $data['data'] ?? []);
        } catch (RequestException $e) {
            return [];
        }
    }

    public function getPlatformName(): string
    {
        return 'CurseForge';
    }

    private function formatAddon(array $addon): array
    {
        return [
            'id' => $addon['id'],
            'name' => $addon['name'],
            'description' => $addon['summary'] ?? '',
            'downloadCount' => $addon['downloadCount'] ?? 0,
            'iconUrl' => $addon['logo']['url'] ?? '',
            'author' => $addon['authors'][0]['name'] ?? 'Unknown',
            'platform' => 'curseforge',
            'categories' => array_map(fn($cat) => $cat['name'], $addon['categories'] ?? []),
            'gameVersions' => $addon['latestFilesIndexes'] ? 
                array_unique(array_map(fn($idx) => $idx['gameVersion'], $addon['latestFilesIndexes'])) : [],
        ];
    }

    private function formatVersion(array $version): array
    {
        return [
            'id' => $version['id'],
            'name' => $version['displayName'],
            'fileName' => $version['fileName'],
            'releaseType' => $version['releaseType'],
            'gameVersions' => $version['gameVersions'] ?? [],
            'downloadUrl' => $version['downloadUrl'] ?? '',
            'fileDate' => $version['fileDate'],
            'fileLength' => $version['fileLength'],
        ];
    }

    private function getCategoryId(string $category): ?int
    {
        $categories = [
            'mods' => 6,
            'plugins' => 5,
            'technology' => 4558,
            'adventure' => 4472,
            'magic' => 4473,
        ];

        return $categories[$category] ?? null;
    }
}