<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ajustes_anteriores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_conciliada_id')
                ->constrained('contas_conciliadas')
                ->onDelete('cascade');

            $table->date('data');
            $table->string('doc', 15);
            $table->string('numero_nota', 10);
            $table->decimal('valor', 15, 2)->comment("Valor do ajuste anterior.\r\nValor do ajuste anterior realizado no balanco.");
            $table->enum('tipo', ['D', 'C'])->comment("Tipo do ajuste anterior.\r\nD=Débito\r\nC=Crédito");
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ajustes_anteriores');
    }
};
