<?php

namespace Pterodactyl\Services\Addons;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Storage;
use Pterodactyl\Models\Server;

class ModrinthService implements AddonServiceInterface
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.modrinth.com/v2/',
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
                'query' => $query,
                'limit' => 20,
                'offset' => ($page - 1) * 20,
                'facets' => [],
            ];

            if ($gameVersion) {
                $params['facets'][] = "versions:{$gameVersion}";
            }

            if ($category) {
                $params['facets'][] = "categories:{$category}";
            }

            // Format facets as JSON array
            if (!empty($params['facets'])) {
                $params['facets'] = '[["' . implode('"],["', $params['facets']) . '"]]';
            } else {
                unset($params['facets']);
            }

            $response = $this->client->get('search', [
                'query' => $params,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'data' => array_map([$this, 'formatAddon'], $data['hits'] ?? []),
                'pagination' => [
                    'page' => $page,
                    'totalCount' => $data['total_hits'] ?? 0,
                ],
            ];
        } catch (RequestException $e) {
            return ['data' => [], 'pagination' => ['page' => $page, 'totalCount' => 0]];
        }
    }

    public function getAddon(string $addonId): array
    {
        try {
            $response = $this->client->get("project/{$addonId}");

            $data = json_decode($response->getBody()->getContents(), true);

            return $this->formatAddon($data);
        } catch (RequestException $e) {
            return [];
        }
    }

    public function getVersions(string $addonId, string $gameVersion = ''): array
    {
        try {
            $params = [];
            if ($gameVersion) {
                $params['game_versions'] = "[\"{$gameVersion}\"]";
            }

            $response = $this->client->get("project/{$addonId}/version", [
                'query' => $params,
            ]);

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
            
            // Get version info
            $response = $this->client->get("version/{$versionId}");
            $versionData = json_decode($response->getBody()->getContents(), true);

            // Get primary file
            $primaryFile = null;
            foreach ($versionData['files'] as $file) {
                if ($file['primary'] ?? false) {
                    $primaryFile = $file;
                    break;
                }
            }

            if (!$primaryFile) {
                $primaryFile = $versionData['files'][0] ?? null;
            }

            if (!$primaryFile) {
                return [
                    'success' => false,
                    'error' => 'No downloadable file found',
                ];
            }

            $fileName = $primaryFile['filename'];
            $downloadUrl = $primaryFile['url'];

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
                'size' => $primaryFile['size'],
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
            $response = $this->client->get('tag/game_version');

            $data = json_decode($response->getBody()->getContents(), true);

            return array_map(function ($version) {
                return [
                    'id' => $version['version'],
                    'name' => $version['version'],
                    'type' => $version['version_type'],
                ];
            }, $data ?? []);
        } catch (RequestException $e) {
            return [];
        }
    }

    public function getPlatformName(): string
    {
        return 'Modrinth';
    }

    private function formatAddon(array $addon): array
    {
        return [
            'id' => $addon['project_id'] ?? $addon['id'] ?? '',
            'name' => $addon['title'] ?? $addon['name'] ?? '',
            'description' => $addon['description'] ?? '',
            'downloadCount' => $addon['downloads'] ?? 0,
            'iconUrl' => $addon['icon_url'] ?? '',
            'author' => $addon['author'] ?? 'Unknown',
            'platform' => 'modrinth',
            'categories' => $addon['categories'] ?? [],
            'gameVersions' => $addon['versions'] ?? [],
        ];
    }

    private function formatVersion(array $version): array
    {
        $primaryFile = null;
        foreach ($version['files'] as $file) {
            if ($file['primary'] ?? false) {
                $primaryFile = $file;
                break;
            }
        }
        if (!$primaryFile) {
            $primaryFile = $version['files'][0] ?? [];
        }

        return [
            'id' => $version['id'],
            'name' => $version['name'],
            'fileName' => $primaryFile['filename'] ?? '',
            'releaseType' => $version['version_type'],
            'gameVersions' => $version['game_versions'] ?? [],
            'downloadUrl' => $primaryFile['url'] ?? '',
            'fileDate' => $version['date_published'],
            'fileLength' => $primaryFile['size'] ?? 0,
        ];
    }
}