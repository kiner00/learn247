import { onBeforeUnmount, watch } from 'vue';
import Hls from 'hls.js';

/**
 * Attach an HLS source to a <video> element ref.
 *
 * - Uses native HLS on Safari / iOS (which support `application/vnd.apple.mpegurl`).
 * - Falls back to hls.js elsewhere.
 * - Cleans up on unmount or when the source changes.
 *
 * @param {import('vue').Ref<HTMLVideoElement|null>} videoRef
 * @param {import('vue').Ref<string|null|undefined>} sourceRef
 */
export function useHlsPlayer(videoRef, sourceRef) {
    let instance = null;

    function destroy() {
        if (instance) {
            instance.destroy();
            instance = null;
        }
    }

    function attach() {
        const video  = videoRef.value;
        const source = sourceRef.value;

        destroy();
        if (!video || !source) return;

        if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = source;
            return;
        }

        if (Hls.isSupported()) {
            instance = new Hls();
            instance.loadSource(source);
            instance.attachMedia(video);
        } else {
            video.src = source;
        }
    }

    watch([videoRef, sourceRef], attach, { immediate: true });
    onBeforeUnmount(destroy);

    return { destroy };
}
