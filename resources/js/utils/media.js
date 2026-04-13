import { usePage } from '@inertiajs/vue3';

export function resolveMediaUrl(url) {
    if (!url) return url;
    if (/^(https?:)?\/\//i.test(url) || url.startsWith('/')) return url;

    const base = usePage().props?.s3_base_url || '';
    if (!base) return url;

    return `${base}/${url.replace(/^\/+/, '')}`;
}
