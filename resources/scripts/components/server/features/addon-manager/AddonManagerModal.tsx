import React, { useState, useEffect } from 'react';
import { ServerContext } from '@/state/server';
import Modal from '@/components/elements/Modal';
import tw from 'twin.macro';
import Button from '@/components/elements/Button';
import FlashMessageRender from '@/components/FlashMessageRender';
import useFlash from '@/plugins/useFlash';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Select from '@/components/elements/Select';
import Field from '@/components/elements/Field';
import { Form, Formik } from 'formik';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import { object, string } from 'yup';
import http from '@/api/http';

interface Addon {
    id: string;
    name: string;
    description: string;
    downloadCount: number;
    iconUrl: string;
    author: string;
    platform: string;
    categories: string[];
    gameVersions: string[];
}

interface InstalledAddon {
    type: string;
    name: string;
    fileName: string;
    path: string;
    size: number;
    lastModified: number;
}

interface World {
    name: string;
    path: string;
    size: number;
    lastModified: number;
    type: string;
}

interface Props {
    visible: boolean;
    onDismissed: () => void;
}

const AddonManagerModal = ({ visible, onDismissed }: Props) => {
    const [activeTab, setActiveTab] = useState('search');
    const [searchResults, setSearchResults] = useState<{[key: string]: {data: Addon[]}}>();
    const [installedAddons, setInstalledAddons] = useState<InstalledAddon[]>([]);
    const [worlds, setWorlds] = useState<World[]>([]);
    const [loading, setLoading] = useState(false);
    const [searchLoading, setSearchLoading] = useState(false);

    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { clearFlashes, clearAndAddHttpError } = useFlash();

    useEffect(() => {
        if (visible) {
            loadInstalledAddons();
            loadWorlds();
        }
    }, [visible]);

    const loadInstalledAddons = async () => {
        try {
            const response = await http.get(`/api/client/servers/${uuid}/addons/installed`);
            setInstalledAddons(response.data.data);
        } catch (error) {
            clearAndAddHttpError({ key: 'addon-manager', error });
        }
    };

    const loadWorlds = async () => {
        try {
            const response = await http.get(`/api/client/servers/${uuid}/addons/worlds`);
            setWorlds(response.data.data);
        } catch (error) {
            clearAndAddHttpError({ key: 'addon-manager', error });
        }
    };

    const searchAddons = async (values: {query: string, platform: string, gameVersion: string}) => {
        setSearchLoading(true);
        try {
            const response = await http.get(`/api/client/servers/${uuid}/addons/search`, {
                params: values,
            });
            setSearchResults(response.data.data);
        } catch (error) {
            clearAndAddHttpError({ key: 'addon-manager', error });
        } finally {
            setSearchLoading(false);
        }
    };

    const installAddon = async (platform: string, addonId: string, versionId: string) => {
        setLoading(true);
        try {
            await http.post(`/api/client/servers/${uuid}/addons/install`, {
                platform,
                addonId,
                versionId,
            });
            loadInstalledAddons();
        } catch (error) {
            clearAndAddHttpError({ key: 'addon-manager', error });
        } finally {
            setLoading(false);
        }
    };

    const uninstallAddon = async (fileName: string, type: string) => {
        setLoading(true);
        try {
            await http.delete(`/api/client/servers/${uuid}/addons/uninstall`, {
                data: { fileName, type },
            });
            loadInstalledAddons();
        } catch (error) {
            clearAndAddHttpError({ key: 'addon-manager', error });
        } finally {
            setLoading(false);
        }
    };

    const exportAddons = async (format: string) => {
        setLoading(true);
        try {
            const response = await http.post(`/api/client/servers/${uuid}/addons/export`, {
                format,
            });
            
            // Trigger download
            window.open(response.data.data.downloadUrl, '_blank');
        } catch (error) {
            clearAndAddHttpError({ key: 'addon-manager', error });
        } finally {
            setLoading(false);
        }
    };

    const createWorld = async (values: {worldName: string}) => {
        setLoading(true);
        try {
            await http.post(`/api/client/servers/${uuid}/addons/worlds`, values);
            loadWorlds();
        } catch (error) {
            clearAndAddHttpError({ key: 'addon-manager', error });
        } finally {
            setLoading(false);
        }
    };

    const deleteWorld = async (worldName: string) => {
        setLoading(true);
        try {
            await http.delete(`/api/client/servers/${uuid}/addons/worlds`, {
                data: { worldName },
            });
            loadWorlds();
        } catch (error) {
            clearAndAddHttpError({ key: 'addon-manager', error });
        } finally {
            setLoading(false);
        }
    };

    const exportWorld = async (worldName: string) => {
        setLoading(true);
        try {
            const response = await http.post(`/api/client/servers/${uuid}/addons/worlds/export`, {
                worldName,
            });
            
            // Trigger download
            window.open(response.data.data.downloadUrl, '_blank');
        } catch (error) {
            clearAndAddHttpError({ key: 'addon-manager', error });
        } finally {
            setLoading(false);
        }
    };

    const formatBytes = (bytes: number) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const renderSearchTab = () => (
        <div>
            <Formik
                initialValues={{
                    query: '',
                    platform: 'all',
                    gameVersion: '',
                }}
                validationSchema={object().shape({
                    query: string().required(),
                })}
                onSubmit={searchAddons}
            >
                {({ isSubmitting }) => (
                    <Form css={tw`mb-6`}>
                        <div css={tw`grid grid-cols-1 md:grid-cols-3 gap-4 mb-4`}>
                            <Field
                                label={'Search Query'}
                                name={'query'}
                                description={'Search for mods, plugins, or resources'}
                            />
                            <div>
                                <label css={tw`block text-xs uppercase text-neutral-200 mb-2`}>
                                    Platform
                                </label>
                                <Select name={'platform'}>
                                    <option value={'all'}>All Platforms</option>
                                    <option value={'curseforge'}>CurseForge</option>
                                    <option value={'modrinth'}>Modrinth</option>
                                    <option value={'spigotmc'}>SpigotMC</option>
                                </Select>
                            </div>
                            <div>
                                <label css={tw`block text-xs uppercase text-neutral-200 mb-2`}>
                                    Game Version
                                </label>
                                <Select name={'gameVersion'}>
                                    <option value={''}>Any Version</option>
                                    <option value={'1.21'}>1.21</option>
                                    <option value={'1.20.6'}>1.20.6</option>
                                    <option value={'1.20.4'}>1.20.4</option>
                                    <option value={'1.19.4'}>1.19.4</option>
                                </Select>
                            </div>
                        </div>
                        <Button type={'submit'} disabled={isSubmitting || searchLoading}>
                            {searchLoading ? 'Searching...' : 'Search'}
                        </Button>
                    </Form>
                )}
            </Formik>

            {searchResults && (
                <div css={tw`space-y-4`}>
                    {Object.entries(searchResults).map(([platform, results]) => (
                        <TitledGreyBox key={platform} title={`${platform.charAt(0).toUpperCase() + platform.slice(1)} Results`}>
                            <div css={tw`grid grid-cols-1 gap-3`}>
                                {results.data.slice(0, 5).map((addon) => (
                                    <div key={addon.id} css={tw`flex items-center justify-between p-3 bg-neutral-700 rounded`}>
                                        <div css={tw`flex items-center space-x-3`}>
                                            {addon.iconUrl && (
                                                <img src={addon.iconUrl} alt={addon.name} css={tw`w-8 h-8 rounded`} />
                                            )}
                                            <div>
                                                <h4 css={tw`text-sm font-medium text-neutral-100`}>{addon.name}</h4>
                                                <p css={tw`text-xs text-neutral-400`}>by {addon.author}</p>
                                                <p css={tw`text-xs text-neutral-300`}>{addon.description}</p>
                                            </div>
                                        </div>
                                        <Button
                                            size={'xsmall'}
                                            onClick={() => installAddon(addon.platform, addon.id, '')}
                                        >
                                            Install
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </TitledGreyBox>
                    ))}
                </div>
            )}
        </div>
    );

    const renderInstalledTab = () => (
        <div>
            <div css={tw`flex justify-between items-center mb-4`}>
                <h3 css={tw`text-lg font-medium text-neutral-100`}>Installed Addons</h3>
                <div css={tw`space-x-2`}>
                    <Button size={'small'} onClick={() => exportAddons('json')}>
                        Export JSON
                    </Button>
                    <Button size={'small'} onClick={() => exportAddons('csv')}>
                        Export CSV
                    </Button>
                </div>
            </div>
            
            <div css={tw`space-y-2`}>
                {installedAddons.map((addon) => (
                    <div key={addon.path} css={tw`flex items-center justify-between p-3 bg-neutral-700 rounded`}>
                        <div>
                            <h4 css={tw`text-sm font-medium text-neutral-100`}>{addon.name}</h4>
                            <p css={tw`text-xs text-neutral-400`}>
                                {addon.type} • {formatBytes(addon.size)} • {new Date(addon.lastModified * 1000).toLocaleDateString()}
                            </p>
                        </div>
                        <Button
                            size={'xsmall'}
                            color={'red'}
                            onClick={() => uninstallAddon(addon.fileName, addon.type)}
                        >
                            Uninstall
                        </Button>
                    </div>
                ))}
                {installedAddons.length === 0 && (
                    <p css={tw`text-center text-neutral-400 py-8`}>No addons installed</p>
                )}
            </div>
        </div>
    );

    const renderWorldsTab = () => (
        <div>
            <div css={tw`flex justify-between items-center mb-4`}>
                <h3 css={tw`text-lg font-medium text-neutral-100`}>World Management</h3>
                <Formik
                    initialValues={{ worldName: '' }}
                    validationSchema={object().shape({
                        worldName: string().required(),
                    })}
                    onSubmit={createWorld}
                >
                    {({ isSubmitting }) => (
                        <Form css={tw`flex space-x-2`}>
                            <Field name={'worldName'} placeholder={'New world name'} />
                            <Button type={'submit'} size={'small'} disabled={isSubmitting}>
                                Create World
                            </Button>
                        </Form>
                    )}
                </Formik>
            </div>
            
            <div css={tw`space-y-2`}>
                {worlds.map((world) => (
                    <div key={world.name} css={tw`flex items-center justify-between p-3 bg-neutral-700 rounded`}>
                        <div>
                            <h4 css={tw`text-sm font-medium text-neutral-100`}>{world.name}</h4>
                            <p css={tw`text-xs text-neutral-400`}>
                                {world.type} • {formatBytes(world.size)} • {new Date(world.lastModified * 1000).toLocaleDateString()}
                            </p>
                        </div>
                        <div css={tw`space-x-2`}>
                            <Button size={'xsmall'} onClick={() => exportWorld(world.name)}>
                                Export
                            </Button>
                            <Button
                                size={'xsmall'}
                                color={'red'}
                                onClick={() => deleteWorld(world.name)}
                            >
                                Delete
                            </Button>
                        </div>
                    </div>
                ))}
                {worlds.length === 0 && (
                    <p css={tw`text-center text-neutral-400 py-8`}>No worlds found</p>
                )}
            </div>
        </div>
    );

    return (
        <Modal
            visible={visible}
            onDismissed={onDismissed}
            closeOnBackground={false}
            showSpinnerOverlay={loading}
            css={tw`w-full max-w-4xl`}
        >
            <FlashMessageRender key={'addon-manager'} css={tw`mb-4`} />
            <div css={tw`mb-6`}>
                <h2 css={tw`text-2xl mb-4 text-neutral-100`}>Minecraft Addon Manager</h2>
                
                <div css={tw`flex space-x-4 mb-6 border-b border-neutral-700`}>
                    <button
                        css={[
                            tw`px-4 py-2 text-sm font-medium transition-colors duration-150`,
                            activeTab === 'search' ? tw`text-primary-400 border-b-2 border-primary-400` : tw`text-neutral-400 hover:text-neutral-200`,
                        ]}
                        onClick={() => setActiveTab('search')}
                    >
                        Search & Install
                    </button>
                    <button
                        css={[
                            tw`px-4 py-2 text-sm font-medium transition-colors duration-150`,
                            activeTab === 'installed' ? tw`text-primary-400 border-b-2 border-primary-400` : tw`text-neutral-400 hover:text-neutral-200`,
                        ]}
                        onClick={() => setActiveTab('installed')}
                    >
                        Installed Addons
                    </button>
                    <button
                        css={[
                            tw`px-4 py-2 text-sm font-medium transition-colors duration-150`,
                            activeTab === 'worlds' ? tw`text-primary-400 border-b-2 border-primary-400` : tw`text-neutral-400 hover:text-neutral-200`,
                        ]}
                        onClick={() => setActiveTab('worlds')}
                    >
                        World Management
                    </button>
                </div>
            </div>

            <div css={tw`mb-6 max-h-96 overflow-y-auto`}>
                {activeTab === 'search' && renderSearchTab()}
                {activeTab === 'installed' && renderInstalledTab()}
                {activeTab === 'worlds' && renderWorldsTab()}
            </div>

            <div css={tw`flex justify-end`}>
                <Button isSecondary onClick={onDismissed}>
                    Close
                </Button>
            </div>
        </Modal>
    );
};

export default AddonManagerModal;