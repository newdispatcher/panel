<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Server;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Services\Addons\CurseForgeService;
use Pterodactyl\Services\Addons\ModrinthService;
use Pterodactyl\Services\Addons\SpigotMCService;
use Pterodactyl\Services\Addons\WorldManagerService;
use Pterodactyl\Transformers\Api\Client\AddonsTransformer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AddonController extends ClientApiController
{
    private CurseForgeService $curseForgeService;
    private ModrinthService $modrinthService;
    private SpigotMCService $spigotMCService;
    private WorldManagerService $worldManagerService;

    public function __construct(
        CurseForgeService $curseForgeService,
        ModrinthService $modrinthService,
        SpigotMCService $spigotMCService,
        WorldManagerService $worldManagerService
    ) {
        parent::__construct();
        
        $this->curseForgeService = $curseForgeService;
        $this->modrinthService = $modrinthService;
        $this->spigotMCService = $spigotMCService;
        $this->worldManagerService = $worldManagerService;
    }

    /**
     * Search for addons across platforms.
     */
    public function search(Request $request, Server $server): JsonResponse
    {
        $query = $request->input('query', '');
        $platform = $request->input('platform', 'all');
        $gameVersion = $request->input('gameVersion', '');
        $category = $request->input('category', '');
        $page = (int) $request->input('page', 1);

        $results = [];

        if ($platform === 'all' || $platform === 'curseforge') {
            $curseForgeResults = $this->curseForgeService->search($query, $gameVersion, $category, $page);
            $results['curseforge'] = $curseForgeResults;
        }

        if ($platform === 'all' || $platform === 'modrinth') {
            $modrinthResults = $this->modrinthService->search($query, $gameVersion, $category, $page);
            $results['modrinth'] = $modrinthResults;
        }

        if ($platform === 'all' || $platform === 'spigotmc') {
            $spigotMCResults = $this->spigotMCService->search($query, $gameVersion, $category, $page);
            $results['spigotmc'] = $spigotMCResults;
        }

        return new JsonResponse([
            'object' => 'list',
            'data' => $results,
        ]);
    }

    /**
     * Get addon details from a specific platform.
     */
    public function getAddon(Request $request, Server $server): JsonResponse
    {
        $platform = $request->input('platform');
        $addonId = $request->input('addonId');

        $service = $this->getServiceForPlatform($platform);
        if (!$service) {
            return new JsonResponse(['error' => 'Invalid platform'], 400);
        }

        $addon = $service->getAddon($addonId);

        return new JsonResponse([
            'object' => 'addon',
            'data' => $addon,
        ]);
    }

    /**
     * Get versions for an addon.
     */
    public function getVersions(Request $request, Server $server): JsonResponse
    {
        $platform = $request->input('platform');
        $addonId = $request->input('addonId');
        $gameVersion = $request->input('gameVersion', '');

        $service = $this->getServiceForPlatform($platform);
        if (!$service) {
            return new JsonResponse(['error' => 'Invalid platform'], 400);
        }

        $versions = $service->getVersions($addonId, $gameVersion);

        return new JsonResponse([
            'object' => 'list',
            'data' => $versions,
        ]);
    }

    /**
     * Download and install an addon.
     */
    public function installAddon(Request $request, Server $server): JsonResponse
    {
        $platform = $request->input('platform');
        $addonId = $request->input('addonId');
        $versionId = $request->input('versionId');

        $service = $this->getServiceForPlatform($platform);
        if (!$service) {
            return new JsonResponse(['error' => 'Invalid platform'], 400);
        }

        $result = $service->downloadAddon($server->uuid, $addonId, $versionId);

        return new JsonResponse([
            'object' => 'installation_result',
            'data' => $result,
        ]);
    }

    /**
     * List installed addons.
     */
    public function listInstalled(Request $request, Server $server): JsonResponse
    {
        $type = $request->input('type', 'all'); // mods, plugins, or all
        $disk = Storage::disk('local');
        $installed = [];

        if ($type === 'all' || $type === 'mods') {
            $modsPath = "servers/{$server->uuid}/mods";
            if ($disk->exists($modsPath)) {
                $modFiles = $disk->files($modsPath);
                foreach ($modFiles as $file) {
                    if (str_ends_with($file, '.jar')) {
                        $installed[] = [
                            'type' => 'mod',
                            'name' => basename($file, '.jar'),
                            'fileName' => basename($file),
                            'path' => $file,
                            'size' => $disk->size($file),
                            'lastModified' => $disk->lastModified($file),
                        ];
                    }
                }
            }
        }

        if ($type === 'all' || $type === 'plugins') {
            $pluginsPath = "servers/{$server->uuid}/plugins";
            if ($disk->exists($pluginsPath)) {
                $pluginFiles = $disk->files($pluginsPath);
                foreach ($pluginFiles as $file) {
                    if (str_ends_with($file, '.jar')) {
                        $installed[] = [
                            'type' => 'plugin',
                            'name' => basename($file, '.jar'),
                            'fileName' => basename($file),
                            'path' => $file,
                            'size' => $disk->size($file),
                            'lastModified' => $disk->lastModified($file),
                        ];
                    }
                }
            }
        }

        return new JsonResponse([
            'object' => 'list',
            'data' => $installed,
        ]);
    }

    /**
     * Uninstall an addon.
     */
    public function uninstallAddon(Request $request, Server $server): JsonResponse
    {
        $fileName = $request->input('fileName');
        $type = $request->input('type', 'mod');

        $disk = Storage::disk('local');
        $filePath = "servers/{$server->uuid}/" . ($type === 'plugin' ? 'plugins' : 'mods') . "/{$fileName}";

        if (!$disk->exists($filePath)) {
            return new JsonResponse(['error' => 'File not found'], 404);
        }

        $disk->delete($filePath);

        return new JsonResponse([
            'object' => 'uninstall_result',
            'data' => [
                'success' => true,
                'fileName' => $fileName,
            ],
        ]);
    }

    /**
     * Export installed addons list.
     */
    public function exportAddons(Request $request, Server $server): JsonResponse
    {
        $format = $request->input('format', 'json'); // json or csv
        $type = $request->input('type', 'all');

        $installed = $this->listInstalled($request, $server)->getData(true)['data'];
        
        if ($format === 'csv') {
            $csv = "Name,Type,File Name,Size,Last Modified\n";
            foreach ($installed as $item) {
                $csv .= sprintf(
                    "%s,%s,%s,%d,%s\n",
                    $item['name'],
                    $item['type'],
                    $item['fileName'],
                    $item['size'],
                    date('Y-m-d H:i:s', $item['lastModified'])
                );
            }
            
            $fileName = "addons_export_" . date('Y-m-d_H-i-s') . '.csv';
            $filePath = "exports/{$server->uuid}/{$fileName}";
            
            Storage::disk('local')->makeDirectory("exports/{$server->uuid}");
            Storage::disk('local')->put($filePath, $csv);
        } else {
            $fileName = "addons_export_" . date('Y-m-d_H-i-s') . '.json';
            $filePath = "exports/{$server->uuid}/{$fileName}";
            
            Storage::disk('local')->makeDirectory("exports/{$server->uuid}");
            Storage::disk('local')->put($filePath, json_encode($installed, JSON_PRETTY_PRINT));
        }

        return new JsonResponse([
            'object' => 'export_result',
            'data' => [
                'success' => true,
                'fileName' => $fileName,
                'downloadUrl' => route('api.client.servers.addons.download-export', [
                    'server' => $server->uuid,
                    'file' => $fileName,
                ]),
            ],
        ]);
    }

    /**
     * Download exported file.
     */
    public function downloadExport(Request $request, Server $server, string $fileName): mixed
    {
        $filePath = "exports/{$server->uuid}/{$fileName}";
        $disk = Storage::disk('local');

        if (!$disk->exists($filePath)) {
            return new JsonResponse(['error' => 'File not found'], 404);
        }

        return $disk->download($filePath, $fileName);
    }

    /**
     * World management endpoints.
     */
    public function listWorlds(Request $request, Server $server): JsonResponse
    {
        $worlds = $this->worldManagerService->listWorlds($server->uuid);

        return new JsonResponse([
            'object' => 'list',
            'data' => $worlds,
        ]);
    }

    public function createWorld(Request $request, Server $server): JsonResponse
    {
        $worldName = $request->input('worldName');
        $options = $request->input('options', []);

        $result = $this->worldManagerService->createWorld($server->uuid, $worldName, $options);

        return new JsonResponse([
            'object' => 'world_creation_result',
            'data' => $result,
        ]);
    }

    public function deleteWorld(Request $request, Server $server): JsonResponse
    {
        $worldName = $request->input('worldName');

        $result = $this->worldManagerService->deleteWorld($server->uuid, $worldName);

        return new JsonResponse([
            'object' => 'world_deletion_result',
            'data' => $result,
        ]);
    }

    public function exportWorld(Request $request, Server $server): JsonResponse
    {
        $worldName = $request->input('worldName');

        $result = $this->worldManagerService->exportWorld($server->uuid, $worldName);

        return new JsonResponse([
            'object' => 'world_export_result',
            'data' => $result,
        ]);
    }

    public function importWorld(Request $request, Server $server): JsonResponse
    {
        $worldName = $request->input('worldName');
        $file = $request->file('worldFile');

        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['error' => 'No file provided'], 400);
        }

        $tempPath = $file->store('temp');
        $fullPath = storage_path("app/{$tempPath}");

        $result = $this->worldManagerService->importWorld($server->uuid, $fullPath, $worldName);

        return new JsonResponse([
            'object' => 'world_import_result',
            'data' => $result,
        ]);
    }

    /**
     * Get supported game versions.
     */
    public function getSupportedVersions(Request $request): JsonResponse
    {
        $platform = $request->input('platform', 'all');
        $versions = [];

        if ($platform === 'all' || $platform === 'curseforge') {
            $versions['curseforge'] = $this->curseForgeService->getSupportedVersions();
        }

        if ($platform === 'all' || $platform === 'modrinth') {
            $versions['modrinth'] = $this->modrinthService->getSupportedVersions();
        }

        if ($platform === 'all' || $platform === 'spigotmc') {
            $versions['spigotmc'] = $this->spigotMCService->getSupportedVersions();
        }

        return new JsonResponse([
            'object' => 'list',
            'data' => $versions,
        ]);
    }

    private function getServiceForPlatform(string $platform)
    {
        return match ($platform) {
            'curseforge' => $this->curseForgeService,
            'modrinth' => $this->modrinthService,
            'spigotmc' => $this->spigotMCService,
            default => null,
        };
    }
}