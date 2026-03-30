<?php

namespace App\Livewire;

use App\Models\ContaConciliada;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class ConciliacaoAjustes extends Component implements HasForms, HasTable, HasActions
{
    use InteractsWithForms;
    use InteractsWithTable;
    use InteractsWithActions;

    public $conciliacaoId;

    public function mount($conciliacaoId)
    {
        $this->conciliacaoId = $conciliacaoId;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ContaConciliada::query()
                ->where('conciliacao_id', $this->conciliacaoId)
            )
            ->selectable()
            ->columns([
                TextColumn::make('numero')
                    ->label('Número'),
                TextColumn::make('nome')
                    ->label('Nome'),
                TextColumn::make('mascara_contabil')
                    ->label('Máscara Contábil'),
                IconColumn::make('balanceado')
                    ->toggleable()
                    ->boolean(),
            ])
            ->filters([
                Filter::make('balanceado')
                    ->label('Balanceado')
                    ->schema([
                        Select::make('balanceado')
                            ->options([
                                1 => 'Sim',
                                0 => 'Não',
                            ])
                            ->default(0)
                            ->placeholder('Todos'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $value = $data['balanceado'] ?? null;

                        if ($value === null || $value === '') {
                            return $query;
                        }

                        return $query->where('balanceado', (int) $value);
                    })
                    ->indicateUsing(function (array $data): ?string {
                        $value = $data['balanceado'] ?? null;

                        if ($value === null || $value === '') {
                            return null;
                        }

                        return 'Balanceado: ' . (($value) ? 'Sim' : 'Não');
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('selecionar_para_proxima_etapa')
                    ->label('Selecionar para próxima etapa')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        $ids = $records->pluck('id')->toArray();
                        session()->put('contas_para_conciliar', $ids);
                    })
                    ->successNotificationTitle('Contas marcadas para próxima etapa'),
            ]);
    }

    public function render()
    {
        return view('livewire.conciliacao-ajustes');
    }
}
