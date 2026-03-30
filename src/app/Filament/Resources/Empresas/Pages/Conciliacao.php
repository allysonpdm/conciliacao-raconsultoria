<?php

namespace App\Filament\Resources\Empresas\Pages;

use App\Filament\Resources\Empresas\EmpresaResource;
use App\Models\Conciliacao as ModelsConciliacao;
use App\Services\ConciliacaoService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class Conciliacao extends Page
{
    use InteractsWithRecord;

    protected static string $resource = EmpresaResource::class;

    protected string $view = 'filament.resources.empresas.pages.conciliacao';

    protected static ?string $title = 'Conciliação';

    public ?array $data = [];

    private bool $isSaving = false;

    public bool $fileProcessed = false;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->form->fill($this->getRecord()?->attributesToArray());
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function form(Schema $schema): Schema
    {
        $userId = Auth::id();
        $refreshKey = $this->fileProcessed ? now()->timestamp : 'initial';

        return $schema
            ->components([
                Wizard::make([
                    Step::make('Subir o Arquivo')
                        ->icon(Heroicon::DocumentArrowUp)
                        ->schema([
                            Form::make([
                                FileUpload::make('file')
                                    ->preserveFilenames()
                                    ->disk('uploads')
                                    ->directory("user/{$userId}")
                                    ->visibility('public')
                                    ->label('Arquivo do balanço')
                                    ->required()
                                    ->maxFiles(1)
                                    ->disabled(fn(string $operation): bool => $operation === 'edit')
                                    ->acceptedFileTypes(['text/csv', 'text/plain'])
                                    ->maxSize(10240)
                                    ->loadingIndicatorPosition('right')
                                    ->removeUploadedFileButtonPosition('right')
                                    ->uploadButtonPosition('right')
                                    #->storeFiles(false)
                                    ->downloadable()
                                    ->previewable()
                                    ->columnSpanFull()
                                    ->live()
                                    ->afterStateUpdated(function () {
                                        $this->save();
                                    }),
                            ])
                                ->columns(3)
                        ]),
                    Step::make('Visão Geral')
                        ->icon(Heroicon::ChartBar)
                        ->description('Resumo dos dados processados.')
                        ->schema([
                            Livewire::make(
                                component: \App\Livewire\ConciliacaoOverview::class,
                                data: ['conciliacaoId' => $this->getRecord()?->id]
                            )
                                ->key("conciliacao-overview-widget-{$this->getRecord()?->id}-{$refreshKey}")
                        ]),
                    Step::make('Selecionar Contas')
                        ->icon('heroicon-o-list-bullet')
                        ->description('Selecione as contas desejadas e clique em "Confirmar contas e continuar" para prosseguir.')
                        ->schema([
                            Livewire::make(
                                component: \App\Livewire\ConciliacaoAjustes::class,
                                data: ['conciliacaoId' => $this->getRecord()?->id]
                            )
                                ->key("conciliacao-ajustes-widget-{$this->getRecord()?->id}-{$refreshKey}")
                        ]),
                    Step::make('Corrigir Erros')
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->description('Revise e corrija possíveis erros humanos identificados nas contas selecionadas.')
                        ->schema([
                            Livewire::make(
                                component: \App\Livewire\CorrigirErros::class,
                                data: ['conciliacaoId' => $this->getRecord()?->id]
                            )
                                ->key("conciliacao-erros-widget-{$this->getRecord()?->id}-{$refreshKey}")
                        ]),
                    Step::make('Notas Fiscais')
                        ->icon(Heroicon::DocumentCurrencyDollar)
                        ->description('Notas não pagas, pagas com desconto e pagas com juros das contas selecionadas.')
                        ->schema([
                            Livewire::make(
                                component: \App\Livewire\NotasFiscais::class,
                                data: ['conciliacaoId' => $this->getRecord()?->id]
                            )
                                ->key("conciliacao-notas-widget-{$this->getRecord()?->id}-{$refreshKey}")
                        ]),
                    Step::make('Pagamentos sem Nota')
                        ->icon(Heroicon::Banknotes)
                        ->description('Pagamentos das contas selecionadas que não possuem nota fiscal correspondente.')
                        ->schema([
                            Livewire::make(
                                component: \App\Livewire\Pagamentos::class,
                                data: ['conciliacaoId' => $this->getRecord()?->id]
                            )
                                ->key("conciliacao-pagamentos-widget-{$this->getRecord()?->id}-{$refreshKey}")
                        ]),
                    Step::make('Exportação')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->description('Gere o arquivo de exportação com os registros selecionados.')
                        ->schema([
                            // Não implementado ainda
                        ]),
                ])->key('conciliacao-wizard')
                    ->nextAction(fn($action) => $action->extraAttributes([
                        'x-show' => 'getStepIndex(step) !== 2',
                        'x-on:click' => "if (getStepIndex(step) === 4 || getStepIndex(step) === 5) { window.dispatchEvent(new CustomEvent('marcar-para-exportacao')); } else { requestNextStep(); }",
                    ]))
                    ->skippable(fn() => $this->getRecord()->file !== null)
            ])
            ->record($this->getRecord())
            ->statePath('data');
    }

    public function getRecord(): ?ModelsConciliacao
    {
        return $this->record?->conciliacao;
    }

    public function save(): void
    {
        if ($this->isSaving) {
            return;
        }

        $this->isSaving = true;

        $data = $this->form->getState();
        $record = $this->getRecord();

        /** @var ConciliacaoService $service */
        $service = new ConciliacaoService;

        $record->fill($data);
        $record->save();

        try {
            $service->store(
                empresa: $this->record,
                pathFile: $data['file'],
            );

            // Marca que o arquivo foi processado com sucesso
            $this->fileProcessed = true;
            $this->dispatch('refresh-components');

        } catch (\Exception $e) {
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();

            $this->isSaving = false;
            return;
        }

        Notification::make()
            ->success()
            ->title('Salvo')
            ->send();

        $this->isSaving = false;
    }
}
