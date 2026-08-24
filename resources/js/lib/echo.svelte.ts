import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import type {
    FrameCapturedEvent,
    LayerUpdatedEvent,
    RemoteCommandReceivedEvent,
    VideoStatusUpdatedEvent,
} from '@/types/echo';
import { PROJECT_EVENTS } from '@/types/echo';

let echo: Echo<'reverb'> | null = null;

/**
 * Lazily created so pages that never subscribe (the projects index)
 * never open a websocket connection.
 */
export function getEcho(): Echo<'reverb'> {
    echo ??= new Echo({
        broadcaster: 'reverb',
        Pusher,
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    return echo;
}

export interface ProjectChannelHandlers {
    onRemoteCommand?: (event: RemoteCommandReceivedEvent) => void;
    onFrameCaptured?: (event: FrameCapturedEvent) => void;
    onVideoStatusUpdated?: (event: VideoStatusUpdatedEvent) => void;
    onLayerUpdated?: (event: LayerUpdatedEvent) => void;
}

/**
 * Subscribes to a project's public channel and returns a teardown
 * function, so the call can be used directly as an $effect body.
 */
export function subscribeToProjectChannel(
    remoteToken: string,
    handlers: ProjectChannelHandlers,
): () => void {
    const channelName = `project.${remoteToken}`;
    const channel = getEcho().channel(channelName);

    if (handlers.onRemoteCommand) {
        channel.listen(PROJECT_EVENTS.remoteCommand, handlers.onRemoteCommand);
    }

    if (handlers.onFrameCaptured) {
        channel.listen(PROJECT_EVENTS.frameCaptured, handlers.onFrameCaptured);
    }

    if (handlers.onVideoStatusUpdated) {
        channel.listen(
            PROJECT_EVENTS.videoStatusUpdated,
            handlers.onVideoStatusUpdated,
        );
    }

    if (handlers.onLayerUpdated) {
        channel.listen(PROJECT_EVENTS.layerUpdated, handlers.onLayerUpdated);
    }

    return () => getEcho().leaveChannel(channelName);
}

export type ConnectionState =
    'connecting' | 'connected' | 'unavailable' | 'failed' | 'disconnected';

const connection = $state({ state: 'connecting' as ConnectionState });

let watchingConnection = false;

/**
 * Exposes the websocket connection state as reactive rune state for
 * connection indicators.
 */
export function watchConnection(): { readonly state: ConnectionState } {
    if (!watchingConnection) {
        watchingConnection = true;

        const pusher = (getEcho().connector as unknown as { pusher: Pusher })
            .pusher;

        connection.state = pusher.connection.state as ConnectionState;

        pusher.connection.bind(
            'state_change',
            ({ current }: { current: string }) => {
                connection.state = current as ConnectionState;
            },
        );
    }

    return connection;
}
