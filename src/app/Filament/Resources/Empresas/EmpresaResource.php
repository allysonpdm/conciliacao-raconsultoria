<?php

namespace App\Filament\Resources\Empresas;

use App\Filament\Resources\Empresas\Pages\Conciliacao;
use App\Filament\Resources\Empresas\Pages\Config;
use App\Filament\Resources\Empresas\Pages\CreateEmpresa;
use App\Filament\Resources\Empresas\Pages\EditEmpresa;
use App\Filament\Resources\Empresas\Pages\ListEmpresas;
use App\Filament\Resources\Empresas\Resources\Conciliacaos\Pages\ManageContas;
use App\Filament\Resources\Empresas\Schemas\EmpresaForm;
use App\Filament\Resources\Empresas\Tables\EmpresasTable;
use App\Models\Empresa;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EmpresaResource extends Resource
{
    protected static ?string $model = Empresa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingOffice;

    protected static ?string $recordTitleAttribute = 'nome';
    protected static ?string $pluralLabel = 'Empresas';
    protected static ?string $navigationLabel = 'Empresas';


    public static function form(Schema $schema): Schema
    {
        return EmpresaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmpresasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmpresas::route('/'),
            'create' => CreateEmpresa::route('/create'),
            'edit' => EditEmpresa::route('/{record}/edit'),
            'conciliacao' => Conciliacao::route('/{record}/conciliacao'),
            'config' => Config::route('/{record}/config'),
            #'manage-contas' => ManageContas::route('/{record}/manage-contas'),
        ];
    }
}
