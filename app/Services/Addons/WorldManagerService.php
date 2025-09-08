<?php

namespace Pterodactyl\Services\Addons;

use Illuminate\Support\Facades\Storage;
use Pterodactyl\Models\Server;
use ZipArchive;

class WorldManagerService
{
    /**
     * List all worlds for a server.
     *
     * @param string $serverUuid
     * @return array
     */
    public function listWorlds(string $serverUuid): array
    {
        $server = Server::where('uuid', $serverUuid)->firstOrFail();
        $serverPath = "servers/{$server->uuid}";
        
        $worlds = [];
        $disk = Storage::disk('local');
        
        // Common world directories
        $worldPaths = [
            'world',
            'world_nether',
            'world_the_end',
        ];
        
        // Also check for custom world folders
        $directories = $disk->directories($serverPath);
        foreach ($directories as $dir) {
            $dirName = basename($dir);
            if ($disk->exists("{$dir}/level.dat") || $disk->exists("{$dir}/region")) {
                $worldPaths[] = $dirName;
            }
        }
        
        $worldPaths = array_unique($worldPaths);
        
        foreach ($worldPaths as $worldName) {
            $worldPath = "{$serverPath}/{$worldName}";
            
            if ($disk->exists("{$worldPath}/level.dat") || $disk->exists("{$worldPath}/region")) {
                $size = $this->calculateDirectorySize($worldPath);
                $lastModified = $this->getLastModified($worldPath);
                
                $worlds[] = [
                    'name' => $worldName,
                    'path' => $worldName,
                    'size' => $size,
                    'lastModified' => $lastModified,
                    'type' => $this->detectWorldType($worldPath),
                ];
            }
        }
        
        return $worlds;
    }

    /**
     * Create a new world.
     *
     * @param string $serverUuid
     * @param string $worldName
     * @param array $options
     * @return array
     */
    public function createWorld(string $serverUuid, string $worldName, array $options = []): array
    {
        try {
            $server = Server::where('uuid', $serverUuid)->firstOrFail();
            $worldPath = "servers/{$server->uuid}/{$worldName}";
            $disk = Storage::disk('local');
            
            if ($disk->exists($worldPath)) {
                return [
                    'success' => false,
                    'error' => 'World already exists',
                ];
            }
            
            // Create world directory
            $disk->makeDirectory($worldPath);
            $disk->makeDirectory("{$worldPath}/region");
            $disk->makeDirectory("{$worldPath}/data");
            
            // Create basic level.dat file
            $levelDat = $this->createBasicLevelDat($worldName, $options);
            $disk->put("{$worldPath}/level.dat", $levelDat);
            
            return [
                'success' => true,
                'worldName' => $worldName,
                'path' => $worldPath,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a world.
     *
     * @param string $serverUuid
     * @param string $worldName
     * @return array
     */
    public function deleteWorld(string $serverUuid, string $worldName): array
    {
        try {
            $server = Server::where('uuid', $serverUuid)->firstOrFail();
            $worldPath = "servers/{$server->uuid}/{$worldName}";
            $disk = Storage::disk('local');
            
            if (!$disk->exists($worldPath)) {
                return [
                    'success' => false,
                    'error' => 'World does not exist',
                ];
            }
            
            $disk->deleteDirectory($worldPath);
            
            return [
                'success' => true,
                'worldName' => $worldName,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Export a world as a ZIP file.
     *
     * @param string $serverUuid
     * @param string $worldName
     * @return array
     */
    public function exportWorld(string $serverUuid, string $worldName): array
    {
        try {
            $server = Server::where('uuid', $serverUuid)->firstOrFail();
            $worldPath = "servers/{$server->uuid}/{$worldName}";
            $disk = Storage::disk('local');
            
            if (!$disk->exists($worldPath)) {
                return [
                    'success' => false,
                    'error' => 'World does not exist',
                ];
            }
            
            $zipFileName = "{$worldName}_" . date('Y-m-d_H-i-s') . '.zip';
            $zipPath = "exports/{$server->uuid}/{$zipFileName}";
            
            // Ensure export directory exists
            $disk->makeDirectory("exports/{$server->uuid}");
            
            $zip = new ZipArchive();
            $zipFullPath = storage_path("app/{$zipPath}");
            
            if ($zip->open($zipFullPath, ZipArchive::CREATE) !== TRUE) {
                return [
                    'success' => false,
                    'error' => 'Could not create ZIP file',
                ];
            }
            
            $this->addDirectoryToZip($zip, storage_path("app/{$worldPath}"), $worldName);
            $zip->close();
            
            return [
                'success' => true,
                'fileName' => $zipFileName,
                'path' => $zipPath,
                'downloadUrl' => route('api.client.servers.addons.download-export', [
                    'server' => $server->uuid,
                    'file' => $zipFileName,
                ]),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Import a world from a ZIP file.
     *
     * @param string $serverUuid
     * @param string $filePath
     * @param string $worldName
     * @return array
     */
    public function importWorld(string $serverUuid, string $filePath, string $worldName): array
    {
        try {
            $server = Server::where('uuid', $serverUuid)->firstOrFail();
            $worldPath = "servers/{$server->uuid}/{$worldName}";
            $disk = Storage::disk('local');
            
            if ($disk->exists($worldPath)) {
                return [
                    'success' => false,
                    'error' => 'World already exists',
                ];
            }
            
            $zip = new ZipArchive();
            if ($zip->open($filePath) !== TRUE) {
                return [
                    'success' => false,
                    'error' => 'Could not open ZIP file',
                ];
            }
            
            $extractPath = storage_path("app/{$worldPath}");
            $zip->extractTo($extractPath);
            $zip->close();
            
            // Clean up uploaded file
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            return [
                'success' => true,
                'worldName' => $worldName,
                'path' => $worldPath,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function calculateDirectorySize(string $path): int
    {
        $disk = Storage::disk('local');
        $files = $disk->allFiles($path);
        $size = 0;
        
        foreach ($files as $file) {
            $size += $disk->size($file);
        }
        
        return $size;
    }

    private function getLastModified(string $path): int
    {
        $disk = Storage::disk('local');
        $files = $disk->allFiles($path);
        $lastModified = 0;
        
        foreach ($files as $file) {
            $modified = $disk->lastModified($file);
            if ($modified > $lastModified) {
                $lastModified = $modified;
            }
        }
        
        return $lastModified;
    }

    private function detectWorldType(string $path): string
    {
        $disk = Storage::disk('local');
        
        if ($disk->exists("{$path}/DIM-1")) {
            return 'nether';
        } elseif ($disk->exists("{$path}/DIM1")) {
            return 'end';
        } else {
            return 'overworld';
        }
    }

    private function createBasicLevelDat(string $worldName, array $options): string
    {
        // This is a simplified level.dat creation
        // In a real implementation, you'd want to create a proper NBT format file
        return base64_decode('H4sIAAAAAAAAAHNKzk9JAA=='); // Empty compressed NBT
    }

    private function addDirectoryToZip(ZipArchive $zip, string $dir, string $zipPath): void
    {
        if (is_dir($dir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = $zipPath . '/' . substr($filePath, strlen($dir) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }
    }
}