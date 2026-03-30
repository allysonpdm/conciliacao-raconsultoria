<?php

namespace App\Filament\Resources\Empresas\Pages;

use App\Filament\Resources\Empresas\EmpresaResource;
use App\Rules\UniqueContaConciliacaoForUser;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class EditEmpresa extends EditRecord
{
    protected static string $resource = EmpresaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('Conciliar')
                ->icon(Heroicon::Scale)
                ->color(Color::Indigo)
                ->url(fn(): string => route(name: 'filament.conciliacao.resources.empresas.conciliacao', parameters: ['record' => $this->record])),
            Action::make('Configurações')
                ->icon(Heroicon::Cog6Tooth)
                ->color(Color::Indigo)
                ->url(fn(): string => route(name: 'filament.conciliacao.resources.empresas.config', parameters: ['record' => $this->record])),

            #Action::make('Gerenciar Contas')
            #    ->icon(Heroicon::Banknotes)
            #    ->color(Color::Indigo)
            #    ->url(fn(): string => route(name: 'filament.conciliacao.resources.empresas.manage-contas', parameters: ['record' => $this->record])),
            DeleteAction::make()
                ->icon(Heroicon::Trash),
        ];
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }
}
