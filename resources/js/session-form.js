import Alpine from 'alpinejs';

/**
 * Counseling Session form (Schedule + Edit, shared `_form` partial):
 * per-field error tooltips plus an instant client-side check before submit.
 *
 * Mirrors the established field-error pattern (New Assessment Student
 * step, Login): an invalid field gets a red border and a red tooltip
 * anchored under it; typing in the field hides the tooltip but the red
 * border stays until the form is resubmitted; on load the first invalid
 * field is scrolled into view and focused.
 *
 * `errors` starts as the server's validation errors (field => message).
 * The client-side check only covers the fields it can decide on its own
 * (`required` fields + the follow-up date); everything else is still
 * validated by CounselingSessionFormRequest, which remains the backstop.
 */
Alpine.data('sessionForm', (initialErrors = {}, followUpRequired = false, messages = {}) => ({
    errors: { ...initialErrors },
    shown: Object.fromEntries(Object.keys(initialErrors).map((field) => [field, true])),
    followUpRequired,

    init() {
        const form = this.$root.closest('form');

        form?.addEventListener('submit', (event) => this.validate(event, form));

        this.$nextTick(() => this.focusFirstError());
    },

    hasError(field) {
        return Boolean(this.errors[field]);
    },

    showsTooltip(field) {
        return Boolean(this.errors[field] && this.shown[field]);
    },

    hideTooltip(field) {
        this.shown[field] = false;
    },

    toggleFollowUp(checked) {
        if (!checked) {
            delete this.errors.follow_up_date;
        }
    },

    validate(event, form) {
        const value = (name) => (form.elements[name]?.value ?? '').trim();
        const clientErrors = {};

        for (const field of ['session_date', 'session_time', 'session_notes']) {
            // data-optional: unreadable stored notes, kept when left empty.
            if (value(field) === '' && !form.elements[field]?.hasAttribute('data-optional')) {
                clientErrors[field] = messages[field];
            }
        }

        if (this.followUpRequired && value('follow_up_date') === '') {
            clientErrors.follow_up_date = messages.follow_up_date;
        }

        for (const field of ['session_date', 'session_time', 'session_notes', 'follow_up_date']) {
            delete this.errors[field];
        }

        if (Object.keys(clientErrors).length === 0) {
            return;
        }

        event.preventDefault();

        this.errors = { ...this.errors, ...clientErrors };
        this.shown = Object.fromEntries(Object.keys(this.errors).map((field) => [field, true]));

        this.$nextTick(() => this.focusFirstError());
    },

    focusFirstError() {
        const first = [...this.$root.querySelectorAll('[name]')].find(
            (element) => element.type !== 'hidden' && this.hasError(element.name),
        );

        if (first) {
            first.scrollIntoView({ behavior: 'smooth', block: 'center' });
            first.focus({ preventScroll: true });
        }
    },
}));
