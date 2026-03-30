<?php

namespace App\Livewire;

use App\Models\Conciliacao;
use App\Models\ContaConciliada;
use App\Models\Nota;
use Dom\Text;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class NotasFiscais extends Component implements HasForms, HasTable, HasActions
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
            ->query(
                Nota::whereHas(
                    'contaConciliada',
                function ($query) {
                    $query->where('conciliacao_id', $this->conciliacaoId);
                })
                ->with('contaConciliada.pagamentos')
            )
            ->searchable([
                // Only provide a custom closure so we control the exact SQL generated
                function (Builder $query, string $search) {
                    $like = "%{$search}%";

                    // Qualify the main table column explicitly
                    $query->orWhere('notas.numero', 'like', $like);

                    // Also search the related contaConciliada.nome
                    $query->orWhereHas('contaConciliada', function (Builder $q) use ($like) {
                        $q->where('nome', 'like', $like);
                    });

                    return $query;
                },
            ])
            ->groups([
                Group::make('ContaConciliada.nome')
                    ->label('Conta Conciliada')
                    ->getTitleFromRecordUsing(fn($record): string => "{$record->contaConciliada->numero} - {$record->contaConciliada?->nome}" ?? 'Sem conta conciliada')
                    ->getDescriptionFromRecordUsing(fn($record) => $record->contaConciliada?->balanceado ? 'Balanceada' : 'Não balanceada')
                    ->collapsible(),
                Group::make('data')->label('Data')
                    ->getTitleFromRecordUsing(fn($record): string => $record->data?->format('d/m/Y') ?? 'Sem data')
                    ->collapsible(),
            ])
            ->collapsedGroupsByDefault()
            ->defaultGroup('ContaConciliada.nome')
            ->columns([
                //IconColumn::make('ContaConciliada.balanceado')
                //    ->boolean()
                //    ->label('Balanceado'),
                TextColumn::make('ContaConciliada.nome')
                    ->label('Nome')
                    ->searchable(isIndividual: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('numero')
                    ->label('Número')
                    ->sortable(),
                TextColumn::make('data')
                    ->date('d/m/Y')
                    ->label('Data')
                    ->sortable(),
                TextColumn::make('valor')
                    ->searchable()
                    ->label('Valor')
                    ->money('BRL'),
                TextColumn::make('valor_pago')
                    ->searchable()
                    ->label('Valor Pago')
                    ->money('BRL'),
                TextColumn::make('juros')
                    ->label('Juros')
                    ->getStateUsing(function ($record) {
                        if ($record->tipo === 'com_juros_paga') {
                            $juros = (floatval($record->valor_pago ?? 0) - floatval($record->valor ?? 0));
                            return $juros > 0 ? $juros : null;
                        }
                        return null;
                    })
                    ->money('BRL')
                    ->colors([
                        'danger' => fn($state): bool => floatval($state ?? 0) > 0,
                    ]),
                TextColumn::make('desconto')
                    ->label('Desconto')
                    ->getStateUsing(function ($record) {
                        if ($record->tipo === 'desconto_paga') {
                            $desconto = (floatval($record->valor ?? 0) - floatval($record->valor_pago ?? 0));
                            return $desconto > 0 ? $desconto : null;
                        }
                        return null;
                    })
                    ->money('BRL')
                    ->colors([
                        'success' => fn($state): bool => floatval($state ?? 0) > 0,
                    ]),
                TextColumn::make('tipo')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'paga' => 'Paga',
                        'nao_paga' => 'Não paga',
                        'parcialmente_paga' => 'Parcialmente paga',
                        'desconto_paga' => 'Paga com desconto',
                        'com_juros_paga' => 'Paga com juros',
                        default => (string) $state,
                    })
                    ->colors([
                        'success' => fn($state): bool => in_array($state, ['paga', 'desconto_paga']),
                        'danger' => fn($state): bool => in_array($state, ['nao_paga', 'com_juros_paga']),
                        'warning' => fn($state): bool => $state === 'parcialmente_paga',
                    ])
                    ->summarize([
                        Count::make()
                            ->query(fn($query) => $query->where('tipo', 'paga'))
                            ->label('Pagas'),
                        Count::make()
                            ->query(fn($query) => $query->where('tipo', 'nao_paga'))
                            ->label('Não pagas'),
                        Count::make()
                            ->query(fn($query) => $query->where('tipo', 'parcialmente_paga'))
                            ->label('Parcialmente pagas'),
                        Count::make()
                            ->query(fn($query) => $query->where('tipo', 'desconto_paga'))
                            ->label('Pagas com desconto'),
                        Count::make()
                            ->query(fn($query) => $query->where('tipo', 'com_juros_paga'))
                            ->label('Pagas com juros'),
                    ]),
                ViewColumn::make('pagamentos')
                    ->width('100%')
                    ->label('Pagamentos')
                    ->view('filament.tables.columns.pagamentos'),
            ])
            ->filters([
                SelectFilter::make('conta_conciliada_id')
                    ->label('Conta Conciliada')
                    ->relationship('contaConciliada', 'nome', fn(Builder $query) => $query->where('conciliacao_id', $this->conciliacaoId))
                    ->searchable()
                    ->preload()
                    ->placeholder('Todos'),

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

                        return $query->whereHas('contaConciliada', function (Builder $q) use ($value) {
                            $q->where('balanceado', (int) $value);
                        });
                    })
                    ->indicateUsing(function (array $data): ?string {
                        $value = $data['balanceado'] ?? null;

                        if ($value === null || $value === '') {
                            return null;
                        }

                        return 'Balanceado: ' . (($value) ? 'Sim' : 'Não');
                    }),

                Filter::make('periodo')
                    ->schema([
                        DatePicker::make('periodo_de')->label('De'),
                        DatePicker::make('periodo_ate')->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['periodo_de'] ?? null, fn($q, $date) => $q->whereDate('data', '>=', $date))
                            ->when($data['periodo_ate'] ?? null, fn($q, $date) => $q->whereDate('data', '<=', $date));
                    })
                    ->columns(2)
                    ->columnSpanFull(),

                SelectFilter::make('tipo')
                    ->multiple()
                    ->options([
                        'paga' => 'Paga',
                        'nao_paga' => 'Não paga',
                        'parcialmente_paga' => 'Parcialmente paga',
                        'desconto_paga' => 'Paga com desconto',
                        'com_juros_paga' => 'Paga com juros',
                    ])
                    ->label('Tipo da Nota')
                    ->default([
                        'nao_paga',
                        'desconto_paga',
                        'com_juros_paga',
                    ]),
            ]);
    }

    public function render()
    {
        return view('livewire.notas-fiscais');
    }
}
