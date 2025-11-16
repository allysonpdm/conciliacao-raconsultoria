<?php

namespace App\Filament\Resources\Empresas\Schemas;

use App\Rules\UniqueContaConciliacaoForUser;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class EmpresaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('cnpj')
                    ->label('CNPJ')
                    ->mask('99.999.999/9999-99')
                    ->disabled(),
                TextInput::make('conta_conciliacao')
                    ->label('Conta de Conciliação')
                    ->mask('9999999999')
                    ->live(debounce: 500)
                    ->unique(table: 'empresas', column: 'conta_conciliacao', ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('user_id', Auth::id()))
                    ->rules([
                        'required',
                        'max:10',
                    ])
                    ->validationMessages([
                        'unique' => 'Esta conta de conciliação já está em uso para o seu usuário.',
                    ])
                    ->afterStateUpdated(fn ($state, $set, $livewire) => $livewire->validateOnly('data.conta_conciliacao'))
                    ->maxLength(10)
                    ->required(),
                TextInput::make('nome')
                    ->columnSpan(3)
                    ->afterStateUpdated(fn ($state, $set, $livewire) => $livewire->validateOnly('data.nome'))
                    ->live(debounce: 500)
                    ->required(),
            ])->columns(5);
    }
}
