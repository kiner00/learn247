import { computed, unref } from 'vue';

function formatAmount(amount, currency) {
    const n = Number(amount) || 0;
    return `${currency || 'PHP'} ${n.toLocaleString()}`;
}

function formatWindowDate(raw) {
    if (!raw) return '';
    // Accept both ISO datetimes and date-only strings ("YYYY-MM-DD").
    const d = new Date(raw.length === 10 ? `${raw}T00:00:00` : raw);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

/**
 * Builds the human-readable price note ("Free for 7 days, then PHP 199 first month, PHP 999/month after").
 * Accepts raw community data OR a ref/reactive wrapper so it works in both Settings form preview
 * and LandingHero (which receives the saved community record).
 */
export function usePricingLabel(source) {
    return computed(() => {
        const c = unref(source) ?? {};
        const price = Number(c.price) || 0;
        if (price <= 0) return 'Free to join';

        const currency = c.currency || 'PHP';
        const fmt = (v) => formatAmount(v, currency);
        const suffix = c.billing_type === 'one_time' ? ' one-time' : '/month';

        const parts = [];
        const mode = c.trial_mode || 'none';
        if (mode === 'per_user' && Number(c.trial_days) > 0) {
            parts.push(`Free for ${c.trial_days} days`);
        } else if (mode === 'window' && c.free_until) {
            const formatted = formatWindowDate(c.free_until);
            if (formatted) parts.push(`Free until ${formatted}`);
        }

        const promo = c.first_month_price;
        const hasPromo = promo !== null && promo !== undefined && promo !== '' && Number(promo) < price;

        if (hasPromo) {
            const prefix = parts.length ? 'then ' : '';
            parts.push(`${prefix}${fmt(promo)} first month`);
            parts.push(`${fmt(price)}${suffix} after`);
        } else {
            const prefix = parts.length ? 'then ' : '';
            parts.push(`${prefix}${fmt(price)}${suffix}`);
        }

        return parts.join(', ');
    });
}
