<?php

namespace App\Livewire;

use App\Models\Nota;
use App\Models\Pagamento;
use Dom\Text;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class Pagamentos extends Component implements HasForms, HasTable, HasActions
{
    use InteractsWithForms;
    use InteractsWithTable;
    use InteractsWithActions;

    public $conciliacaoId;

    public function mount(int $conciliacaoId): void
    {
        $this->conciliacaoId = $conciliacaoId;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Pagamento::whereHas('contaConciliada', function ($query) {
                    $query->where('conciliacao_id', $this->conciliacaoId);
                })
                ->with([
                    'contaConciliada',
                    'sugestao'
                ])
            )
            ->searchable([
                function (Builder $query, string $search) {
                    $like = "%{$search}%";

                    // Qualify the main table column explicitly
                    $query->orWhere('numero_nota', 'like', $like);

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
                    ->getTitleFromRecordUsing(fn($record): string =>  "{$record->contaConciliada->numero} - {$record->contaConciliada?->nome}" ?? 'Sem conta conciliada')
                    ->collapsible(),
                Group::make('data')->label('Data')
                    ->getTitleFromRecordUsing(fn($record): string => $record->data?->format('d/m/Y') ?? 'Sem data')
                    ->collapsible(),
                Group::make('numero_nota')->label('Número da Nota')
                    ->getTitleFromRecordUsing(fn($record): string => $record->numero_nota ?? 'Sem número da nota')
                    ->collapsible(),
            ])
            ->collapsedGroupsByDefault()
            ->defaultGroup('ContaConciliada.nome')
            ->columns([
                TextColumn::make('ContaConciliada.nome')
                    ->label('Nome')
                    ->searchable(isIndividual: true)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('numero_nota')
                    ->label('Número da Nota')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('data')
                    ->date('d/m/Y')
                    ->label('Data')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('doc')
                    ->label('Documento')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('valor_nota')
                    ->label('Valor da Nota')
                    ->money('BRL')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('valor_pago')
                    ->label('Valor Pago')
                    ->money('BRL')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('parcela')
                    ->label('Parcela')
                    ->sortable(),
                TextColumn::make('tipo')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'anterior' => 'Pagamento Anterior',
                        'nota não encontrada' => 'Sem nota correspondente',
                        'com juros' => 'Com Juros',
                        'com desconto' => 'Com Desconto',
                        'parcialmente pago' => 'Parcialmente Pago',
                        'pago com nota' => 'Pago com Nota',
                        default => (string) $state,
                    })
                    ->colors([
                        'info' => fn($state): bool => in_array($state, ['anterior']),
                        'success' => fn($state): bool => in_array($state, ['pago com nota', 'com descontos']),
                        'warning' => fn($state): bool => in_array($state, ['parcialmente pago', 'com juros']),
                        'danger' => fn($state): bool => in_array($state, ['nota não encontrada']),
                    ])
                    ->summarize([
                        Count::make()
                            ->query(fn($query) => $query->where('tipo', 'anterior'))
                            ->label('Pagamentos anteriores'),
                        Count::make()
                            ->query(fn($query) => $query->where('tipo', 'pago com nota'))
                            ->label('Com notas'),
                        Count::make()
                            ->query(fn($query) => $query->where('tipo', 'nota não encontrada'))
                            ->label('Sem notas'),
                        Count::make()
                            ->query(fn($query) => $query->where('tipo', 'parcialmente pago'))
                            ->label('Parcialmente pagas'),
                        Count::make()
                            ->query(fn($query) => $query->where('tipo', 'com desconto'))
                            ->label('Pagas com desconto'),
                        Count::make()
                            ->query(fn($query) => $query->where('tipo', 'com juros'))
                            ->label('Pagas com juros'),
                    ]),
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
                        'anterior' => 'Pagamentos Anteriores',
                        'nota não encontrada' => 'Sem nota correspondente',
                        'com juros' => 'Com Juros',
                        'com desconto' => 'Com Desconto',
                        'parcialmente pago' => 'Parcialmente Pago',
                        'pago com nota' => 'Pago com Nota',
                    ])
                    ->label('Tipo do pagamento')
                    ->default([
                        'nota não encontrada',
                    ]),
            ]);
    }

    public function render()
    {
        return view('livewire.pagamentos');
    }
}
