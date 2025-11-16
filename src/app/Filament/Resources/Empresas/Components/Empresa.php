<?php

namespace App\Filament\Resources\Empresas\Components;

use App\ObjectValues\Cnpj;
use App\Rules\CnpjValidationRule;
use App\Rules\UniqueCnpjForUser;
use App\Rules\UniqueContaConciliacaoForUser;
use App\Services\Consultar\Empresa as ConsultarEmpresa;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Group;
use Illuminate\Support\Facades\Auth;

class Empresa
{
    protected function __construct()
    {
        throw new \Exception('Not implemented');
    }

    public static function form(CreateRecord $page): Form
    {
        return Form::make([
            Group::make()
                ->schema([
                    self::fieldsEmpresa(page: $page)
                ])
        ]);
    }

    public static function fieldsEmpresa(CreateRecord $page): Group
    {
        return Group::make()
            ->schema([
                TextInput::make('cnpj')
                    ->label('CNPJ')
                    ->mask('99.999.999/9999-99')
                    ->required()
                    ->rules([
                        new CnpjValidationRule(),
                        new UniqueCnpjForUser(),
                    ])
                    ->live(debounce: 500)
                    ->extraAttributes([
                        'wire:target' => 'data.cnpj',
                    ])
                    ->extraInputAttributes([
                        'wire:loading.attr' => 'disabled',
                        'wire:target' => 'data.cnpj',
                    ])
                    ->afterStateUpdated(function ($set, $state) use ($page) {
                        $page->validateOnly('data.cnpj');
                        // Hidratação: converter string do formulário para Object Value Cnpj
                        try {
                            $cnpj = new Cnpj($state);
                            $empresaService = new ConsultarEmpresa();
                            $dados = $empresaService->consultarCnpj($cnpj);

                            if (!empty($dados) && isset($dados['nome'])) {
                                $set('nome', $dados['nome']);
                            }

                        } catch (\InvalidArgumentException $e) {
                            $set('nome', null);
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Erro ao consultar CNPJ')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->dehydrateStateUsing(function ($state): string {
                        return (new Cnpj($state))->sanitized();
                    }),
                TextInput::make('conta_conciliacao')
                    ->label('Conta de Conciliação')
                    ->maxLength(10)
                    ->mask('9999999999')
                    ->required()
                    ->rules([
                        new UniqueContaConciliacaoForUser()
                    ])
                    ->live(debounce: 500)
                    ->afterStateUpdated(function () use ($page) {
                        $page->validateOnly('data.conta_conciliacao');
                    }),
                TextInput::make('nome')
                    ->minLength(3)
                    ->maxLength(100)
                    ->readOnly()
                    ->trim()
                    ->live(debounce: 500)
                    ->afterStateUpdated(function () use ($page) {
                        $page->validateOnly('data.nome');
                    })
                    ->columnSpan(3),
            ])
            ->columns(5);
    }
}
