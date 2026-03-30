<?php

namespace App\Livewire;

use App\Models\ContaConciliada;
use App\Models\ErroPagamento;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

use Livewire\Component;

class ConciliacaoAjustes extends Component implements HasForms, HasTable, HasActions
{
    use InteractsWithForms;
    use InteractsWithTable;
    use InteractsWithActions;

    public int $conciliacaoId;

    public function mount(int $conciliacaoId): void
    {
        $this->conciliacaoId = $conciliacaoId;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ContaConciliada::query()
                    ->where('conciliacao_id', $this->conciliacaoId)
                    ->orderBy('balanceado', 'asc')
                    ->orderBy('numero', 'asc')
            )
            ->selectable()
            ->columns([
                TextColumn::make('numero')
                    ->label('Número')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('nome')
                    ->label('Nome')
                    ->sortable()
                    ->searchable(),
                IconColumn::make('balanceado')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                TextColumn::make('mascara_contabil')
                    ->label('Máscara Contábil')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('somente_desbalanceadas')
                    ->label('Somente desbalanceadas')
                    ->query(fn(Builder $query) => $query->where('balanceado', false))
                    ->default(),
            ]);
    }

    // JS-driven entrypoint: recebe IDs diretamente do cliente
    public function confirmarSelecionadasFromFooterWithIds(array $ids): array
    {
        if (empty($ids)) {
            Notification::make()
                ->warning()
                ->title('Nenhuma conta selecionada')
                ->body('Selecione pelo menos uma conta antes de continuar.')
                ->send();
            return ['hasErrors' => true];
        }

        session()->put("contas_para_conciliar_{$this->conciliacaoId}", $ids);

        $hasErrors = ErroPagamento::whereIn('conta_conciliada_id', $ids)->exists();

        // Força re-render nos componentes dos steps seguintes para lerem a sessão atualizada
        $this->dispatch('contas-confirmadas');

        Notification::make()
            ->success()
            ->title(count($ids) . ' conta(s) confirmada(s).')
            ->send();

        return ['hasErrors' => $hasErrors];
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.conciliacao-ajustes');
    }
}
