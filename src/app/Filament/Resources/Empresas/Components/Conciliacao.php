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

class Conciliacao
{
    protected function __construct()
    {
        throw new \Exception('Not implemented');
    }

    public static function form(CreateRecord $page): Form
    {
        return Form::make([

        ]);
    }
}
