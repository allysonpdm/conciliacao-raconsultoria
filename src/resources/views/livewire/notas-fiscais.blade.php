<div x-data>
    {{ $this->table }}

    <div class="mt-4 flex justify-end">
        <div x-data="{
            confirmSelected() {
                const component = $el.closest('[wire\\:id]');
                const ids = Array.from((component || document).querySelectorAll('.fi-ta-record-checkbox:checked')).map(el => el.value);
                if (!ids.length) return;
                $wire.call('marcarSelecionadasFromFooterWithIds', ids).then(() => {
                    const wizardEl = document.querySelector('.fi-sc-wizard');
                    if (!wizardEl) return;
                    const wizard = Alpine.$data(wizardEl);
                    if (wizard && wizard.goToNextStep) {
                        wizard.goToNextStep();
                    }
                });
            }
        }" x-init="window.addEventListener('marcar-para-exportacao', () => confirmSelected())">
            <x-filament::button
                color="primary"
                type="button"
                x-on:click="confirmSelected()"
            >
                Marcar para exportação e continuar
            </x-filament::button>
        </div>
    </div>
</div>
