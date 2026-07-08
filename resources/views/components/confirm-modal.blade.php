<script>
    document.addEventListener('alpine:init', () => {
        if (! Alpine.store('confirm')) {
            Alpine.store('confirm', {
                show: false,
                title: '',
                message: '',
                confirmText: 'Confirmar',
                confirmColor: 'brass',
                _onConfirm: null,

                open(title, message, confirmText, confirmColor, onConfirm) {
                    this.title = title;
                    this.message = message;
                    this.confirmText = confirmText;
                    this.confirmColor = confirmColor;
                    this._onConfirm = onConfirm;
                    this.show = true;
                },

                confirm() {
                    if (typeof this._onConfirm === 'function') {
                        this._onConfirm();
                    }
                    this.close();
                },

                close() {
                    this.show = false;
                    this._onConfirm = null;
                },
            });
        }
    });
</script>

<div
    x-data
    x-cloak
    x-show="$store.confirm.show"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 flex items-center justify-center"
    @keydown.escape.window="$store.confirm.close()"
>
    <div
        class="fixed inset-0 bg-ink-900/50"
        @click="$store.confirm.close()"
    ></div>

    <div
        class="relative bg-white border border-ink-100 rounded-lg max-w-sm w-full mx-4 p-6 shadow-xl"
        x-show="$store.confirm.show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @click.outside="$store.confirm.close()"
    >
        <h3 class="font-serif text-lg text-ink-700" x-text="$store.confirm.title"></h3>
        <p class="text-sm text-carbon/70 mt-2" x-text="$store.confirm.message"></p>

        <div class="flex justify-end gap-3 mt-6">
            <button
                type="button"
                class="text-sm font-medium text-carbon/60 hover:text-carbon px-4 py-2 rounded-md transition"
                @click="$store.confirm.close()"
            >
                Cancelar
            </button>
            <button
                type="button"
                :class="$store.confirm.confirmColor === 'rust'
                    ? 'bg-rust hover:bg-rust/90 text-white'
                    : 'bg-brass hover:bg-brass/90 text-white'"
                class="text-sm font-medium px-4 py-2 rounded-md transition"
                @click="$store.confirm.confirm()"
                x-text="$store.confirm.confirmText"
            ></button>
        </div>
    </div>
</div>
