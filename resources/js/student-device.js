/*
 * Student device questionnaire (resources/views/student-device/*). Plain
 * JavaScript, no Alpine, so the page's CSP can forbid eval and inline
 * script. Talks only to the /s endpoints named in the page's data-*
 * attributes, and only ever sends a question id and a 0-3 value.
 *
 * - Every click is autosaved, one request at a time; the newest value per
 *   question wins. Answers tapped before the script ran are sent on start. A failed save is retried with backoff and the status
 *   line says "Not saved — reconnecting…". Done stays disabled until every
 *   required statement is answered and every answer is saved.
 * - The state is polled (every 5 s while answering, 3 s once locked or
 *   held): when the draft is gone the page shows the generic message; when
 *   staff return a locked questionnaire, or let a held one continue, the
 *   page reloads.
 * - The details form at the top of the page (when the student fills in
 *   their own details) is a plain HTML form and needs no script. Until it
 *   is saved the questions are locked (data-locked="1"): autosave doesn't
 *   start, and the state is only watched.
 * - A page restored from the back/forward cache is reloaded, so it always
 *   reflects the server's state.
 */

const ANSWERING_POLL_MS = 5000;
const LOCKED_POLL_MS = 3000;
const MAX_RETRY_MS = 15000;

window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        window.location.reload();
    }
});

const root = document.querySelector('[data-student-device]');

if (root?.dataset.studentDevice === 'questionnaire' && root.dataset.locked === '1') {
    // Details not saved yet: the questions are disabled and nothing is
    // autosaved. Only watch for the draft ending (the generic message) or
    // moving on (reload).
    pollState(root.dataset.stateUrl, ANSWERING_POLL_MS, (state) => {
        if (state === 'identity') {
            return true;
        }

        if (state === 'unavailable') {
            root.replaceChildren(root.querySelector('[data-unavailable-template]').content.cloneNode(true));
        } else {
            window.location.replace(root.dataset.pageUrl);
        }

        return false;
    });
} else if (root?.dataset.studentDevice === 'questionnaire') {
    initQuestionnaire(root);
} else if (root?.dataset.studentDevice === 'held') {
    // Held: reopen the page once the Psychometrician lets it continue (or
    // it ends, which then shows the generic message for good).
    pollState(root.dataset.stateUrl, LOCKED_POLL_MS, (state) => {
        if (state !== 'help') {
            window.location.replace(root.dataset.pageUrl);
            return false;
        }

        return true;
    });
} else if (root?.dataset.studentDevice === 'locked') {
    pollState(root.dataset.stateUrl, LOCKED_POLL_MS, (state) => {
        if (state === 'answering' || state === 'consent') {
            window.location.replace(root.dataset.pageUrl);
            return false;
        }

        // Gone (submitted, cancelled or expired): keep the thank-you message.
        return state === 'locked';
    });
}

async function request(url, method, body) {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
            Accept: 'application/json',
            ...(body === undefined ? {} : { 'Content-Type': 'application/json' }),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    let data = {};

    try {
        data = await response.json();
    } catch {
        // Not JSON (e.g. a proxy error page): treated as a network problem.
    }

    return { status: response.status, data };
}

/**
 * Poll `url`; `onState(state)` returns false to stop. Network errors just
 * wait for the next tick.
 */
function pollState(url, intervalMs, onState) {
    const tick = async () => {
        try {
            const { data } = await request(url, 'GET');

            if (typeof data.state === 'string' && onState(data.state) === false) {
                return;
            }
        } catch {
            // Offline: try again next tick.
        }

        window.setTimeout(tick, intervalMs);
    };

    window.setTimeout(tick, intervalMs);
}

function initQuestionnaire(container) {
    const fieldsets = Array.from(container.querySelectorAll('fieldset[data-question]'));
    const doneButton = container.querySelector('[data-done]');
    const statusLine = container.querySelector('[data-save-status]');
    const counterText = container.querySelector('[data-counter-text]');
    const missingMessage = container.querySelector('[data-missing-message]');
    const total = Number(container.dataset.total);

    const pending = new Map();
    let saving = false;
    let retryMs = 1000;
    let finished = false;

    const answeredCount = () => fieldsets.filter((fieldset) => fieldset.querySelector('input:checked')).length;

    const allRequiredAnswered = () => fieldsets
        .filter((fieldset) => fieldset.dataset.required === '1')
        .every((fieldset) => fieldset.querySelector('input:checked'));

    const setStatus = (key) => {
        statusLine.textContent = container.dataset[key];
    };

    const refresh = () => {
        counterText.textContent = container.dataset.counter
            .replace(':answered', String(answeredCount()))
            .replace(':total', String(total));
        doneButton.disabled = finished || saving || pending.size > 0 || !allRequiredAnswered();
    };

    const showUnavailable = () => {
        finished = true;
        const template = container.querySelector('[data-unavailable-template]');
        container.replaceChildren(template.content.cloneNode(true));
    };

    const reloadPage = () => {
        finished = true;
        window.location.replace(container.dataset.pageUrl);
    };

    // A state the questionnaire can't continue in. `reload`: staff
    // restarted it on a new questionnaire version.
    const handleState = (state) => {
        if (state === 'unavailable') {
            showUnavailable();
        } else if (['locked', 'consent', 'identity', 'help', 'reload'].includes(state)) {
            reloadPage();
        }
    };

    const version = container.dataset.version;

    const flush = async () => {
        if (saving || finished || pending.size === 0) {
            return;
        }

        const [questionId, value] = pending.entries().next().value;
        saving = true;
        setStatus('statusSaving');
        refresh();

        try {
            const { status, data } = await request(container.dataset.answerUrl, 'POST', { question_id: questionId, value, version });

            if (status === 200) {
                if (pending.get(questionId) === value) {
                    pending.delete(questionId);
                }
                retryMs = 1000;
            } else if (status === 404 || status === 409) {
                handleState(data.state ?? 'unavailable');
                return;
            } else if (status === 422) {
                // Not an answer the server takes; drop it rather than loop.
                pending.delete(questionId);
            } else {
                throw new Error(`HTTP ${status}`);
            }
        } catch {
            saving = false;
            setStatus('statusOffline');
            refresh();
            window.setTimeout(flush, retryMs);
            retryMs = Math.min(retryMs * 2, MAX_RETRY_MS);
            return;
        }

        saving = false;

        if (pending.size === 0) {
            setStatus('statusSaved');
            refresh();
        } else {
            flush();
        }
    };

    fieldsets.forEach((fieldset) => {
        fieldset.addEventListener('change', (event) => {
            if (finished || event.target.type !== 'radio') {
                return;
            }

            fieldset.dataset.missing = 'false';
            pending.set(Number(fieldset.dataset.question), Number(event.target.value));
            refresh();
            flush();
        });
    });

    doneButton.addEventListener('click', async () => {
        if (doneButton.disabled) {
            return;
        }

        doneButton.disabled = true;

        try {
            const { status, data } = await request(container.dataset.doneUrl, 'POST', { version });

            if (status === 200 && data.state === 'locked') {
                // Remove the answers from the page before leaving it.
                finished = true;
                container.replaceChildren();
                window.location.replace(container.dataset.pageUrl);
                return;
            }

            if (status === 422 && Array.isArray(data.missing)) {
                const missing = new Set(data.missing.map(Number));
                fieldsets.forEach((fieldset) => {
                    fieldset.dataset.missing = String(missing.has(Number(fieldset.dataset.item)));
                });
                missingMessage.hidden = false;
                fieldsets.find((fieldset) => fieldset.dataset.missing === 'true')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else if (status === 404 || status === 409) {
                handleState(data.state ?? 'unavailable');
                return;
            } else {
                setStatus('statusOffline');
            }
        } catch {
            setStatus('statusOffline');
        }

        refresh();
    });

    // A choice tapped before this script ran (module scripts run only once
    // the page is parsed, so on a slow device a student can be quicker)
    // fired no change event: send every checked answer the server doesn't
    // have yet (data-saved is the server's value).
    fieldsets.forEach((fieldset) => {
        const checked = fieldset.querySelector('input:checked');

        if (checked && checked.value !== fieldset.dataset.saved) {
            pending.set(Number(fieldset.dataset.question), Number(checked.value));
        }
    });
    flush();

    pollState(`${container.dataset.stateUrl}?version=${encodeURIComponent(version)}`, ANSWERING_POLL_MS, (state) => {
        if (finished) {
            return false;
        }

        if (state !== 'answering') {
            handleState(state);
            return false;
        }

        return true;
    });

    refresh();
}
