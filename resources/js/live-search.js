import Alpine from 'alpinejs';

/**
 * Shared live-search behavior for list pages: debounced search-as-you-type
 * plus same-page link interception (pagination, Clear), both swapped in via
 * fetch instead of a full page reload. The server distinguishes a live
 * request from a normal page load via the X-Live-Search header and returns
 * just the results partial in that case (see e.g. StudentController::index).
 */
Alpine.data('liveSearch', () => ({
    async handleInput(event) {
        if (event.target.name !== 'search') {
            return;
        }

        await this.fetchAndSwap(this.buildSearchUrl(event.target));
    },

    handleClick(event) {
        const link = event.target.closest('a');

        if (!link) {
            return;
        }

        const linkUrl = new URL(link.href, window.location.origin);

        if (linkUrl.pathname !== window.location.pathname) {
            return;
        }

        event.preventDefault();
        this.fetchAndSwap(link.href);
    },

    buildSearchUrl(input) {
        const url = new URL(window.location.href);

        if (input.value) {
            url.searchParams.set('search', input.value);
        } else {
            url.searchParams.delete('search');
        }

        url.searchParams.delete('page');

        return url.toString();
    },

    async fetchAndSwap(url) {
        const active = document.activeElement;
        const activeName = active?.getAttribute('name');
        const selectionStart = active?.selectionStart;
        const selectionEnd = active?.selectionEnd;

        const response = await fetch(url, {
            headers: { 'X-Live-Search': 'true' },
        });

        if (!response.ok) {
            return;
        }

        this.$refs.results.innerHTML = await response.text();
        window.history.pushState({}, '', url);

        if (!activeName) {
            return;
        }

        const restored = this.$refs.results.querySelector(`[name="${activeName}"]`);

        if (restored) {
            restored.focus();

            if (typeof selectionStart === 'number') {
                restored.setSelectionRange(selectionStart, selectionEnd);
            }
        }
    },
}));
