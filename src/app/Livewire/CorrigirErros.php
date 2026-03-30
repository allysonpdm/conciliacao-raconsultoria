<?php

namespace App\Livewire;

use App\Models\ErroPagamento;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Attributes\On;
use Livewire\Component;

class CorrigirErros extends Component implements HasForms, HasTable, HasActions
{
    use InteractsWithForms;
    use InteractsWithTable;
    use InteractsWithActions;

    public int $conciliacaoId;
    public bool $hasErrors = false;

    public function mount(int $conciliacaoId): void
    {
        $this->conciliacaoId = $conciliacaoId;
        $selectedContaIds = session("contas_para_conciliar_{$conciliacaoId}", []);
        $this->hasErrors = ErroPagamento::query()
            ->when(
                count($selectedContaIds) > 0,
                fn($q) => $q->whereIn('conta_conciliada_id', $selectedContaIds),
                fn($q) => $q->whereRaw('1 = 0') // sem contas selecionadas = sem erros
            )
            ->exists();
    }

    public function table(Table $table): Table
    {
        $selectedContaIds = session("contas_para_conciliar_{$this->conciliacaoId}", []);

        return $table
            ->query(
                ErroPagamento::query()
                    ->when(
                        count($selectedContaIds) > 0,
                        fn($q) => $q->whereIn('conta_conciliada_id', $selectedContaIds),
                        fn($q) => $q->whereHas(
                            'contaConciliada',
                            fn($q2) => $q2->where('conciliacao_id', $this->conciliacaoId)
                        )
                    )
                    ->with('contaConciliada')
            )
            ->columns([
                TextColumn::make('contaConciliada.nome')
                    ->label('Conta')
                    ->description(fn($record) => $record->contaConciliada?->numero)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('data')
                    ->date('d/m/Y')
                    ->label('Data')
                    ->sortable(),
                TextColumn::make('doc')
                    ->label('Documento')
                    ->searchable(),
                TextColumn::make('numero_nota')
                    ->label('Nº Nota (original)')
                    ->searchable(),
                TextColumn::make('valor_pago')
                    ->label('Valor Pago')
                    ->money('BRL')
                    ->sortable(),
                TextColumn::make('sugestao_numero_nota')
                    ->label('Correção Sugerida')
                    ->badge()
                    ->color('warning')
                    ->placeholder('Sem sugestão'),
            ])
            ->actions([
                EditAction::make()
                    ->label('Corrigir')
                    ->icon('heroicon-o-pencil')
                    ->modalHeading('Corrigir número da nota')
                    ->form([
                        TextInput::make('sugestao_numero_nota')
                            ->label('Número da nota correto')
                            ->default(fn($record) => $record->sugestao_numero_nota)
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update(['sugestao_numero_nota' => $data['sugestao_numero_nota']]);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Correção salva com sucesso')
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Nenhum erro encontrado')
            ->emptyStateDescription('Não foram identificados possíveis erros humanos nas contas selecionadas.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.corrigir-erros');
    }

    #[On('contas-confirmadas')]
    public function atualizarContas(): void
    {
        $selectedContaIds = session("contas_para_conciliar_{$this->conciliacaoId}", []);
        $this->hasErrors = ErroPagamento::query()
            ->when(
                count($selectedContaIds) > 0,
                fn($q) => $q->whereIn('conta_conciliada_id', $selectedContaIds),
                fn($q) => $q->whereRaw('1 = 0')
            )
            ->exists();
    }
}
