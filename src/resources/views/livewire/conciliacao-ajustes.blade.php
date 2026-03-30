<div x-data>
    {{ $this->table }}

    <div class="mt-4 flex justify-end">
        <div x-data="{
            confirmSelected() {
                const component = $el.closest('[wire\\:id]');
                const ids = Array.from((component || document).querySelectorAll('.fi-ta-record-checkbox:checked')).map(el => el.value);
                if (!ids.length) return;
                $wire.call('confirmarSelecionadasFromFooterWithIds', ids).then((res) => {
                    const wizardEl = document.querySelector('.fi-sc-wizard');
                    if (!wizardEl) return;
                    const wizard = Alpine.$data(wizardEl);
                    if (!wizard || !wizard.goToNextStep) return;
                    wizard.goToNextStep();
                    if (!res || !res.hasErrors) {
                        setTimeout(() => wizard.goToNextStep(), 300);
                    }
                });
            }
        }">
            <x-filament::button
                color="primary"
                type="button"
                x-on:click="confirmSelected()"
            >
                Confirmar contas e continuar
            </x-filament::button>
        </div>
    </div>
</div>
