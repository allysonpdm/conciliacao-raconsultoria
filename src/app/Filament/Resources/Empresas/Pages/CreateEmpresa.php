<?php

namespace App\Filament\Resources\Empresas\Pages;

use App\Filament\Resources\Empresas\Components\Config;
use App\Filament\Resources\Empresas\Components\Empresa;
use App\Filament\Resources\Empresas\EmpresaResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateEmpresa extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;
    protected static string $resource = EmpresaResource::class;
    protected static bool $canCreateAnother = false;

    protected function getSteps(): array
    {
        return [
            Step::make('Passo 1: Cadastrar a empresa')
                ->description('Informe o dados básicos')
                ->icon('heroicon-o-building-office')
                ->schema([
                    Empresa::fieldsEmpresa($this)
                ]),

            Step::make('Passo 2: Contas e Históricos')
                ->description('Informe as contas e códigos de histórico')
                ->icon('heroicon-o-banknotes')
                ->schema([
                    Section::make('Contas Contábeis e Códigos de Histórico')
                        ->relationship('config')
                        ->schema([
                            Config::abasContasHistoricos()
                        ])
                ]),

            Step::make('Passo 3: Configurações')
                ->description('Defina os parametros da auditoria')
                ->icon('heroicon-o-cog')
                ->schema([
                    Section::make('Parâmetros da Auditoria')
                        ->relationship('config')
                        ->schema([
                            Config::abasAditoria()
                        ]),
                ]),

            Step::make('Passo 4: Configurações de Importação')
                ->description('')
                ->icon('heroicon-o-check-circle')
                ->schema([
                    Section::make('Confirmação')
                        ->relationship('config')
                        ->schema([
                            Config::abasArquivoExportacao()
                        ]),
                ]),

        ];
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $model = static::getModel()::create($data);
        $model->conciliacao()->create();
        return $model;
    }
}
