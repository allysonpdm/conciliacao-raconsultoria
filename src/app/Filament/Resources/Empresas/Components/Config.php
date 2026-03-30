<?php

namespace App\Filament\Resources\Empresas\Components;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class Config
{

    protected function __construct()
    {
        throw new \Exception('Not implemented');
    }

    public static function form(): Form
    {
        return Form::make([
            Group::make()
            ->schema([
                self::tabsContasHistoricos()
            ])
        ]);
    }

    public static function tabsContasHistoricos(): Tabs
    {
        return Tabs::make('configuracoes')
            ->schema([
                Tab::make('Contas Contábeis e Códigos de Histórico')
                    ->schema([
                        self::abasContasHistoricos()
                    ])->icon('heroicon-o-document-currency-dollar'),
                Tab::make('Auditória')
                    ->schema([
                        self::abasAditoria()
                    ])->icon('heroicon-o-scale'),
                Tab::make('Arquivo de exportação')
                    ->schema([
                        self::abasArquivoExportacao()
                    ])->icon('heroicon-o-document-arrow-up'),
            ]);
    }

    public static function abasContasHistoricos(): Tabs
    {
        return Tabs::make('abas-contas-historicos')
            ->tabs([
                self::tabJuros(),
                self::tabDescontos(),
                self::tabCaixaBanco(),
            ])
            ->vertical();
    }

    public static function tabJuros(): Tabs\Tab
    {
        return Tab::make('Juros')
            ->schema([
                Section::make('Juros')
                    ->label('Informe a conta contábil referente aos juros cobrados ou por atrasos e o código de histórico correspondente.')
                    ->schema([
                        TextInput::make('conta_juros')
                            ->label('Conta')
                            ->mask('9999999999')
                            ->maxLength(10)
                            ->required(),
                        TextInput::make('codigo_historico_juros')
                            ->mask('9999999999')
                            ->label('Código de Histórico')
                            ->maxLength(10)
                            ->required(),
                    ])->columns(3),
            ]);
    }

    public static function tabDescontos(): Tabs\Tab
    {
        return Tab::make('Descontos')
            ->schema([
                Section::make('Descontos')
                    ->label('Informe a conta contábil referente aos descontos condicionais ou incondicionais e o código de histórico correspondente.')
                    ->schema([
                        TextInput::make('conta_descontos')
                            ->label('Conta')
                            ->mask('9999999999')
                            ->maxLength(10)
                            ->required(),
                        TextInput::make('codigo_historico_descontos')
                            ->mask('9999999999')
                            ->label('Código de Histórico')
                            ->maxLength(10)
                            ->required(),
                    ])->columns(3),
            ]);
    }

    public static function tabCaixaBanco(): Tabs\Tab
    {
        return Tab::make('Caixa ou Banco')
            ->schema([
                Section::make('Caixa ou Banco')
                    ->label('Informe a contas contábil da origem do pagamento e o código de histórico correspondente.')
                    ->schema([
                        TextInput::make('conta_pagamentos')
                            ->label('Conta')
                            ->mask('9999999999')
                            ->maxLength(10)
                            ->required(),
                        TextInput::make('codigo_historico_pagamentos')
                            ->mask('9999999999')
                            ->label('Código de Histórico')
                            ->maxLength(10)
                            ->required(),
                    ])->columns(3),
            ]);
    }

    public static function abasAditoria(): Tabs
    {

        return Tabs::make('Auditoria')
            ->tabs([
                self::tabNotasPagasComDescontos(),
                self::tabNotasSemPagamentos(),
            ])
            ->vertical();
    }

    public static function tabNotasPagasComDescontos(): Tab
    {
        return Tab::make('Notas "Pagas com Descontos"')
            ->schema([
                TextInput::make('percentual_min_pago')
                    ->label('Percentual mínimo pago')
                    ->helperText('Percentual mínimo para considerar uma parcela como paga.')
                    ->numeric()
                    ->suffix('%')
                    ->minValue(0)
                    ->maxValue(100)
                    ->step(0.01)
                    ->default(85.00)
                    ->helperText('Define a margem percentual para identificar notas pagas com descontos durante a conciliação.')
                    ->required(),
                TextInput::make('meses_tolerancia_desconto')
                    ->label('Meses de tolerância para descontos')
                    ->helperText('Número de meses de tolerância, após atingir o percentual mínimo pago, para considerar a parcela como paga com desconto.')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(12)
                    ->step(1)
                    ->default(3)
                    ->required(),
            ])->columns(3);
    }

    public static function tabNotasSemPagamentos(): Tab
    {
        return Tab::make('Notas "Sem Pagamentos"')
            ->schema([
                TextInput::make('meses_tolerancia_sem_pagamentos')
                    ->label('Meses de tolerância para sem pagamentos')
                    ->helperText('Número de meses de tolerância para considerar a parcela como sem pagamentos.')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(12)
                    ->step(1)
                    ->default(3)
                    ->required(),
            ])->columns(3);
    }

    public static function abasArquivoExportacao(): Tabs
    {

        return Tabs::make('Abas Arquivo de Exportação')
            ->tabs([
                Tab::make('Pagamentos')
                    ->schema([
                        self::sectionPagamentosFromExportFile(),
                        self::sectionPreferenciasFromExportFile(),
                        self::sectionParcelamentosFromExportFile(),
                    ])
                    ->columns(5)
            ])
            ->vertical();
    }

    public static function sectionPreferenciasFromExportFile(): Section
    {
        return Section::make('Parcela preferencial')
            ->schema([
                Select::make('parcela_preferencial_get')
                    ->label('Obter a data da parcela')
                    ->helperText('Define qual parcela será utilizada para obter data quando uma nota possuir múltiplas parcelas.')
                    ->options([
                        'first' => 'Primeira Parcela',
                        'last' => 'Última Parcela',
                    ])
                    ->columnSpan(2)
                    ->default('last')
            ])
            ->columnSpan(2);
    }

    public static function sectionParcelamentosFromExportFile(): Section
    {
        return Section::make('Parcelamentos')
            ->schema([
                self::groupToggleParcelamento(),
            ])
            ->columns(3)
            ->columnSpanFull();
    }

    public static function groupToggleParcelamento(): Group
    {
        return Group::make()
            ->schema([
                self::toggleParcelar(),
                self::targetParcelar(),
            ])
            ->columns(3)
            ->columnSpanFull();
    }

    public static function toggleParcelar(): Toggle
    {
        return Toggle::make('parcelar')
            ->label('Parcelar pagamentos')
            ->helperText('Habilite para permitir que pagamentos sejam parcelados conforme regras definidas.')
            ->onColor('success')
            ->offColor('danger')
            ->default(true)
            ->live()
            ->columnSpan(3);
    }

    public static function targetParcelar(): Fieldset
    {
        return Fieldset::make('Regras para Parcelamento')
            ->schema([
                self::targetToggleParcelarFields(),
            ])
            ->columnSpanFull()
            ->visible(condition: fn(callable $get): bool => $get('parcelar') === true);
    }

    public static function targetToggleParcelarFields(): Group
    {
        return Group::make()
            ->schema([
                TextInput::make('valor_minimo_parcela')
                    ->label('Parcelar valores acima de: ')
                    ->helperText('Valor mínimo para aplicar o parcelamento automático.')
                    ->live(debounce: 500)
                    ->currencyMask(thousandSeparator: '.', decimalSeparator: ',', precision: 2)
                    ->default(1000.00)
                    ->minValue(0.02)
                    ->prefix('R$')
                    ->nullable()
                    ->required(fn(callable $get) => $get('parcelar') === true),
                TextInput::make('valor_maximo_parcela')
                    ->label('Valor máximo por parcela:')
                    ->helperText('As parcelas não ultrapassam este valor.')
                    ->live(debounce: 500)
                    ->minValue(0.01)
                    ->step(0.01)
                    ->prefix('R$')
                    ->nullable()
                    ->reactive()
                    ->requiredWithout('numero_maximo_parcelas')
                    ->prohibits('numero_maximo_parcelas')
                    ->disabled(fn(callable $get) => !empty($get('numero_maximo_parcelas'))),
                TextInput::make('numero_maximo_parcelas')
                    ->label('Quantidade máxima de parcelas:')
                    ->helperText('Os pagamentos serão criados aleatoriamente não ultrapassando a quantidade de parcelas especificada.')
                    ->live(debounce: 500)
                    ->default(3)
                    ->numeric()
                    ->nullable()
                    ->minValue(2)
                    ->reactive()
                    ->prohibits('valor_maximo_parcela')
                    ->requiredWithout('valor_maximo_parcela')
                    ->disabled(fn(callable $get) => !empty($get('valor_maximo_parcela'))),
                Select::make('parcela_preferencial_set')
                    ->label('Parcela preferencial para diferença de centavos')
                    ->helperText('Define qual parcela será ajustada para cobrir diferenças de centavos no parcelamento.')
                    ->options([
                        'first' => 'Primeira Parcela',
                        'last' => 'Última Parcela',
                    ])
                    ->default('last')
            ])
            ->columns(3)
            ->columnSpanFull();
    }

    public static function sectionPagamentosFromExportFile(): Section
    {
        return Section::make('Datas Automatizadas de Pagamento')
            ->schema([
                self::fieldsetDatasAutomatizadasPagamento(),
            ])
            ->columnSpan(3);
    }

    public static function fieldsetDatasAutomatizadasPagamento(): Fieldset
    {
        return Fieldset::make('Período após a data da nota fiscal')
            ->schema([
                FusedGroup::make([
                    TextInput::make('inicio_periodo_pagamento')
                        ->numeric()
                        ->prefix('De')
                        ->placeholder('Início')
                        ->minValue(1)
                        ->default(15)
                        ->nullable(),
                    TextInput::make('fim_periodo_pagamento')
                        ->prefix('até')
                        ->numeric()
                        ->placeholder('Fim')
                        ->minValue(1)
                        ->default(20)
                        ->nullable(),
                ])
                    ->label('Intervalo em dias')
                    ->columns(2)
                    ->columnSpan(2)
                    ->belowContent(Schema::start([
                        'Determina as datas de pagamento com base na emissão da nota e nas regras de prazo estabelecidas.',
                    ])),
            ]);
    }
}
