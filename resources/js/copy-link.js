/*
 * "Copy link" on the Psychometrician's live page
 * (resources/views/assessments/create/remote.blade.php). The link with the
 * token sits only in the button's data-link attribute: it is never shown
 * as text or as a link, and never sent anywhere but the clipboard.
 *
 * The Clipboard API only works on HTTPS or localhost; on a plain-HTTP LAN
 * address it falls back to the older copy command, and if that fails too,
 * the link is put in a read-only field for a manual Ctrl+C.
 */
export default () => ({
    copied: false,
    failed: false,
    manualLink: '',

    async copy() {
        const link = this.$refs.button.dataset.link;
        this.copied = false;
        this.failed = false;

        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(link);
                this.copied = true;
                return;
            }
        } catch {
            // Refused (permissions): try the older way.
        }

        if (this.copyWithCommand(link)) {
            this.copied = true;
            return;
        }

        this.manualLink = link;
        this.failed = true;
        this.$nextTick(() => this.$refs.manual?.select());
    },

    copyWithCommand(link) {
        const field = document.createElement('textarea');
        field.value = link;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.appendChild(field);
        field.select();

        let ok = false;
        try {
            ok = document.execCommand('copy');
        } catch {
            ok = false;
        }

        field.remove();

        return ok;
    },
});
