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
        Schema::create('notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_conciliada_id')
                ->constrained('contas_conciliadas')
                ->onDelete('cascade');

            $table->string('numero', 10);
            $table->date('data');
            $table->decimal('valor', 15, 2)->comment("Valor da nota fiscal.");
            $table->decimal('valor_pago', 15, 2)->nullable()->comment("Valor pago da nota fiscal.");
            $table->enum('tipo', [
                'paga',
                'nao_paga',
                'parcialmente_paga',
                'desconto_paga',
                'com_juros_paga'
            ]);
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
        Schema::dropIfExists('notas');
    }
};
