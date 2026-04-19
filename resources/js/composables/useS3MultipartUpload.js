import { ref } from 'vue';
import axios from 'axios';

const DEFAULT_CHUNK_SIZE = 10 * 1024 * 1024; // 10 MB
const DEFAULT_CONCURRENCY = 3;

/**
 * Generic S3 multipart-upload composable.
 *
 * The four endpoints (initiate / part-url / complete / abort) must follow the
 * shape implemented by S3MultipartUploadService — i.e. POST to
 *   `${baseUrl}/initiate`, `${baseUrl}/part-url`, `${baseUrl}/complete`, `${baseUrl}/abort`.
 *
 * @param {object} options
 * @param {string} options.baseUrl       e.g. `/communities/${slug}/gallery/videos`
 * @param {number} [options.chunkSize=10MB]
 * @param {number} [options.concurrency=3]
 */
export function useS3MultipartUpload({ baseUrl, chunkSize = DEFAULT_CHUNK_SIZE, concurrency = DEFAULT_CONCURRENCY }) {
    const uploading = ref(false);
    const progress  = ref(0);
    const error     = ref('');

    async function upload(file) {
        uploading.value = true;
        progress.value  = 0;
        error.value     = '';

        let uploadId = null;
        let key      = null;
        let response = null;

        try {
            const { data: initData } = await axios.post(`${baseUrl}/initiate`, {
                filename: file.name,
                content_type: file.type,
                size: file.size,
            });
            uploadId = initData.upload_id;
            key      = initData.key;

            const { default: rawAxios } = await import('axios');
            const s3Client = rawAxios.create({ withCredentials: false });

            const totalChunks    = Math.ceil(file.size / chunkSize);
            const completedParts = [];
            const chunkProgress  = new Array(totalChunks).fill(0);

            const updateProgress = () => {
                const loaded = chunkProgress.reduce((sum, v) => sum + v, 0);
                progress.value = Math.round((loaded / file.size) * 100);
            };

            const uploadChunk = async (partNumber) => {
                const start = (partNumber - 1) * chunkSize;
                const end   = Math.min(start + chunkSize, file.size);
                const chunk = file.slice(start, end);

                const { data: partData } = await axios.post(`${baseUrl}/part-url`, {
                    key, upload_id: uploadId, part_number: partNumber,
                });

                const res = await s3Client.put(partData.url, chunk, {
                    headers: { 'Content-Type': file.type },
                    onUploadProgress: (e) => {
                        chunkProgress[partNumber - 1] = e.loaded;
                        updateProgress();
                    },
                });

                const etag = res.headers['etag'] || res.headers['ETag'];
                completedParts.push({ PartNumber: partNumber, ETag: etag });
            };

            for (let i = 0; i < totalChunks; i += concurrency) {
                const batch = [];
                for (let j = i; j < Math.min(i + concurrency, totalChunks); j++) {
                    batch.push(uploadChunk(j + 1));
                }
                await Promise.all(batch);
            }

            completedParts.sort((a, b) => a.PartNumber - b.PartNumber);

            const completeRes = await axios.post(`${baseUrl}/complete`, {
                key, upload_id: uploadId, parts: completedParts,
            });

            response = completeRes.data;
            return { key, ...response };
        } catch (err) {
            if (uploadId && key) {
                try {
                    await axios.post(`${baseUrl}/abort`, { key, upload_id: uploadId });
                } catch {}
            }

            error.value = extractError(err);
            throw err;
        } finally {
            uploading.value = false;
        }
    }

    return { uploading, progress, error, upload };
}

function extractError(err) {
    if (err?.response?.data?.error)   return err.response.data.error;
    if (err?.response?.data?.message) return err.response.data.message;
    if (typeof err?.response?.data === 'string' && err.response.data.includes('<Message>')) {
        const match = err.response.data.match(/<Message>([^<]+)<\/Message>/);
        return match ? `S3: ${match[1]}` : 'Upload to storage failed.';
    }
    if (err?.message) return err.message;
    return 'Upload failed. Please try again.';
}
