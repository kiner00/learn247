import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Returns a helper to build URLs for a community.
 * On custom/subdomain, strips the /communities/{slug} prefix so the
 * browser address bar stays clean (e.g. thefacelessmarketer.net/courses).
 */
export function useCommunityUrl(communitySlug) {
    const page = usePage();
    const dc   = computed(() => page.props.domain_community);

    const isOnDomain = computed(
        () => dc.value && dc.value.slug === communitySlug
    );

    /**
     * Build a path for this community.
     *  - communityPath('/courses')  → '/courses'              (custom domain)
     *  - communityPath('/courses')  → '/communities/slug/courses'  (normal)
     *  - communityPath()            → '/' or '/communities/slug'
     */
    function communityPath(suffix = '') {
        if (isOnDomain.value) {
            return suffix || '/';
        }
        return `/communities/${communitySlug}${suffix}`;
    }

    return { communityPath, isOnDomain };
}
