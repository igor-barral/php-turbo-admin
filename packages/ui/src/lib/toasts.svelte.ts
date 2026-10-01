export interface Toast {
  id: number;
  kind: 'success' | 'error';
  message: string;
}

let next = 1;
const items = $state<Toast[]>([]);

export const toasts = {
  get items(): Toast[] {
    return items;
  },
  push(kind: Toast['kind'], message: string, timeout = 4000): void {
    const id = next++;
    items.push({ id, kind, message });
    if (timeout > 0) setTimeout(() => toasts.dismiss(id), timeout);
  },
  dismiss(id: number): void {
    const index = items.findIndex((toast) => toast.id === id);
    if (index !== -1) items.splice(index, 1);
  },
};
