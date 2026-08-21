export interface Toast {
    id: number;
    message: string;
    kind: 'success' | 'error';
}

let nextId = 1;

export const toasts = $state<Toast[]>([]);

function push(message: string, kind: Toast['kind']): void {
    const id = nextId++;

    toasts.push({ id, message, kind });

    setTimeout(() => {
        const index = toasts.findIndex((toast) => toast.id === id);

        if (index !== -1) {
            toasts.splice(index, 1);
        }
    }, 4000);
}

export const toast = {
    success: (message: string) => push(message, 'success'),
    error: (message: string) => push(message, 'error'),
};
