/*
 * The Psychometrician's live page for a student-device assessment
 * (resources/views/assessments/create/remote.blade.php). Polls the status
 * endpoint every `interval` ms with `?rev=`; when nothing changed the
 * server sends only the revision, last-seen time, time left and whether
 * the questionnaire version changed. The answers shown are read-only.
 * When the student's own details arrive, or the device is held over a
 * duplicate, the page reloads (the poll carries only those two flags,
 * never the details).
 *
 * Submit and continue to review shows Step 2's "Analyzing responses"
 * overlay (`submitting`) and stops polling: Submit deletes the draft, so
 * a later poll would only get a 404 ("no longer available") behind the
 * overlay, and on php -S it would wait behind the AI call anyway. Back
 * from Step 3 (a page restored from the back/forward cache) hides the
 * overlay and polls again.
 */
export default (config) => ({
    ...config.initial,
    offline: false,
    stopped: false,
    busy: false,
    submitting: false,

    init() {
        this.startPolling();
        this.tickTimer = window.setInterval(() => {
            if (this.seconds_left > 0) {
                this.seconds_left--;
            }
            if (this.last_seen_seconds !== null) {
                this.last_seen_seconds++;
            }
        }, 1000);
    },

    startPolling() {
        this.stopped = false;
        window.clearInterval(this.pollTimer);
        this.pollTimer = window.setInterval(() => this.poll(), config.interval);
    },

    // x-on:submit on the Submit form only.
    submitForReview(event) {
        if (this.submitting) {
            event.preventDefault();
            return;
        }

        this.submitting = true;
        this.stop();
    },

    // x-on:pageshow.window: a page restored from the back/forward cache
    // still has the overlay up and polling stopped.
    restoreAfterBack(event) {
        if (!event.persisted || !this.submitting) {
            return;
        }

        this.submitting = false;

        if (this.active) {
            this.startPolling();
        }
    },

    destroy() {
        this.stop();
        window.clearInterval(this.tickTimer);
    },

    stop() {
        this.stopped = true;
        window.clearInterval(this.pollTimer);
    },

    async poll() {
        if (this.busy || this.stopped) {
            return;
        }

        this.busy = true;

        try {
            const response = await fetch(`${config.statusUrl}?rev=${encodeURIComponent(this.rev)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (response.status === 404) {
                this.state = 'gone';
                this.stop();
                return;
            }

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            this.offline = false;

            // The student's details arrived, or the device was held over a
            // duplicate: reload, so the server renders the details, the
            // correction form and the duplicate-student panel.
            if (data.unchanged === false
                && (data.identity_received !== config.initial.identity_received || data.held !== config.initial.held)) {
                this.stop();
                window.location.reload();
                return;
            }

            Object.assign(this, data);

            if (['expired', 'declined'].includes(this.state)) {
                this.stop();
            }
        } catch {
            this.offline = true;
        } finally {
            this.busy = false;
        }
    },

    get active() {
        return ['pending', 'consent', 'identity', 'held', 'answering', 'locked'].includes(this.state);
    },

    get countdown() {
        const minutes = Math.floor(this.seconds_left / 60);
        const seconds = String(this.seconds_left % 60).padStart(2, '0');

        return `${minutes}:${seconds}`;
    },

    get deviceStatus() {
        return config.statusLabels[this.state] ?? config.statusLabels.gone;
    },

    get lastSeenText() {
        if (this.last_seen_seconds === null) {
            return '';
        }

        return this.last_seen_seconds < 5 ? 'Last seen just now' : `Last seen ${this.last_seen_seconds} s ago`;
    },

    get consentText() {
        if (!this.consent_required) {
            return 'Not shown on the student device (turned off in settings).';
        }

        return this.consented ? 'Acknowledged by the student on their device.' : 'Not acknowledged yet.';
    },

    isSelected(questionId, value) {
        return this.answers[questionId] === value;
    },
});
