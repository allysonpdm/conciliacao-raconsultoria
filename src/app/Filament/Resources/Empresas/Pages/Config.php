<?php

namespace App\Filament\Resources\Empresas\Pages;

use App\Filament\Resources\Empresas\Components\Config as ComponentsConfig;
use App\Filament\Resources\Empresas\EmpresaResource;
use App\Models\Empresa;
use App\Models\Config as EmpresaConfig;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;

class Config extends Page
{
    use InteractsWithRecord;

    protected static string $resource = EmpresaResource::class;

    protected string $view = 'filament.resources.empresas.pages.config';
    protected static ?string $title = 'Configurações da Empresa';

    public ?array $data = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->form->fill($this->getRecord()?->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ComponentsConfig::form()
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Salvar')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                            Action::make('cancel')
                                ->label('Voltar')
                                ->color('gray')
                                ->url(route('filament.conciliacao.resources.empresas.edit', ['record' => $this->record])),
                        ]),
                    ])
            ])
            ->record($this->getRecord())
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $record = $this->getRecord();

        if (empty($record)) {
            throw new \RuntimeException('Configuração da empresa não encontrada.');
        }

        $record->fill($data);
        $record->save();

        Notification::make()
            ->success()
            ->title('Salvo')
            ->send();

        $this->redirect(EmpresaResource::getUrl('edit', ['record' => $record]));
    }

    public function getRecord(): EmpresaConfig
    {
        return $this->record->config;
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }
}
