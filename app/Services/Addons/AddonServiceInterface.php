<?php

namespace Pterodactyl\Services\Addons;

interface AddonServiceInterface
{
    /**
     * Search for addons on the platform.
     *
     * @param string $query
     * @param string $gameVersion
     * @param string $category
     * @param int $page
     * @return array
     */
    public function search(string $query, string $gameVersion = '', string $category = '', int $page = 1): array;

    /**
     * Get addon details by ID.
     *
     * @param string $addonId
     * @return array
     */
    public function getAddon(string $addonId): array;

    /**
     * Get available versions for an addon.
     *
     * @param string $addonId
     * @param string $gameVersion
     * @return array
     */
    public function getVersions(string $addonId, string $gameVersion = ''): array;

    /**
     * Download an addon to the server.
     *
     * @param string $serverUuid
     * @param string $addonId
     * @param string $versionId
     * @return array
     */
    public function downloadAddon(string $serverUuid, string $addonId, string $versionId): array;

    /**
     * Get supported game versions.
     *
     * @return array
     */
    public function getSupportedVersions(): array;

    /**
     * Get platform name.
     *
     * @return string
     */
    public function getPlatformName(): string;
}