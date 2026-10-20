document.addEventListener('alpine:init', () => {
    Alpine.data('stockPilotDialog', (closeAction = null) => ({
        closeAction,
        previouslyFocused: null,
        onKeyDown: null,

        init() {
            const active = document.activeElement;
            this.previouslyFocused = active instanceof HTMLElement ? active : null;
            document.documentElement.classList.add('overflow-hidden');

            this.$nextTick(() => {
                this.focusInitial();
            });

            this.onKeyDown = (event) => {
                if (event.key !== 'Tab' || ! this.$refs.panel) {
                    return;
                }

                const focusable = this.focusableElements();

                if (focusable.length === 0) {
                    event.preventDefault();
                    this.$refs.panel.focus();

                    return;
                }

                const first = focusable[0];
                const last = focusable[focusable.length - 1];

                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (! event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            };

            document.addEventListener('keydown', this.onKeyDown);
        },

        destroy() {
            if (this.onKeyDown) {
                document.removeEventListener('keydown', this.onKeyDown);
                this.onKeyDown = null;
            }

            document.documentElement.classList.remove('overflow-hidden');

            const previous = this.previouslyFocused;

            if (previous instanceof HTMLElement && document.contains(previous)) {
                previous.focus({ preventScroll: true });
            }

            this.previouslyFocused = null;
        },

        focusInitial() {
            const focusable = this.focusableElements();
            const target = focusable[0] || this.$refs.panel;

            target?.focus?.({ preventScroll: true });
        },

        focusableElements() {
            if (! this.$refs.panel) {
                return [];
            }

            return [...this.$refs.panel.querySelectorAll(
                'button:not([disabled]), [href], input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
            )].filter((element) => {
                if (!(element instanceof HTMLElement)) {
                    return false;
                }

                return ! element.hasAttribute('disabled')
                    && element.getAttribute('aria-hidden') !== 'true'
                    && element.tabIndex !== -1
                    && element.offsetParent !== null;
            });
        },

        requestClose() {
            if (! this.closeAction || ! this.$wire) {
                return;
            }

            this.$wire.call(this.closeAction);
        },
    }));
});
