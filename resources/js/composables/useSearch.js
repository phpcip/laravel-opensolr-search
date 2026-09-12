import { reactive } from 'vue';

/**
 * Request state for one JSON endpoint: loading flag, last error, last result.
 *
 * Only the newest request counts. Starting a new one aborts the previous fetch, and a
 * response that arrives out of order is dropped, so fast typing can never paint stale
 * results over fresh ones. Error messages come from the API's JSON body and are rendered
 * as text by the components.
 */
export function useSearch(endpoint) {
    const state = reactive({ loading: false, error: null, result: null });
    let controller = null;
    let sequence = 0;

    async function run(params = {}) {
        controller?.abort();
        controller = new AbortController();
        const id = ++sequence;
        state.loading = true;
        state.error = null;

        const query = new URLSearchParams();
        for (const [key, value] of Object.entries(params)) {
            if (value !== '' && value !== null && value !== undefined) {
                query.set(key, String(value));
            }
        }

        try {
            const response = await fetch(`${endpoint}?${query.toString()}`, {
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(body.message || `Request failed with status ${response.status}`);
            }
            if (id === sequence) {
                state.result = body;
            }
        } catch (error) {
            if (error.name !== 'AbortError' && id === sequence) {
                state.error = error.message;
            }
        } finally {
            if (id === sequence) {
                state.loading = false;
            }
        }
    }

    function reset() {
        controller?.abort();
        sequence += 1;
        state.loading = false;
        state.error = null;
        state.result = null;
    }

    return { state, run, reset };
}
