import React, { useState, useEffect } from 'react';
import { ServerContext } from '@/state/server';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Button from '@/components/elements/Button';
import tw from 'twin.macro';
import AddonManagerModal from './AddonManagerModal';

const AddonManagerFeature = () => {
    const [visible, setVisible] = useState(false);
    const server = ServerContext.useStoreState((state) => state.server.data);

    // Only show for Minecraft servers
    if (!server?.egg?.name.toLowerCase().includes('minecraft')) {
        return null;
    }

    return (
        <>
            <TitledGreyBox title={'Minecraft Addon Manager'} css={tw`mb-4`}>
                <div css={tw`px-1 py-2`}>
                    <p css={tw`text-sm text-neutral-300 mb-4`}>
                        Manage mods, plugins, and worlds for your Minecraft server. Search and install from CurseForge, Modrinth, and SpigotMC.
                    </p>
                    <div css={tw`flex flex-wrap gap-2`}>
                        <Button onClick={() => setVisible(true)} size={'small'}>
                            Open Addon Manager
                        </Button>
                        <Button 
                            onClick={() => setVisible(true)} 
                            size={'small'} 
                            variant={'text'}
                            css={tw`text-neutral-300 hover:text-neutral-100`}
                        >
                            Manage Worlds
                        </Button>
                    </div>
                </div>
            </TitledGreyBox>

            <AddonManagerModal visible={visible} onDismissed={() => setVisible(false)} />
        </>
    );
};

export default AddonManagerFeature;