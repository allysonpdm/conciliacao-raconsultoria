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
        Schema::create('erros_pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_conciliada_id')
                ->constrained('contas_conciliadas')
                ->onDelete('cascade');
            $table->date('data')->comment("Data do pagamento.");
            $table->string('doc', 15)->comment("Documento do pagamento.");
            $table->string('numero_nota', 10)->comment("Número da nota fiscal associada ao pagamento (possivelmente digitado erroneamente).");
            $table->decimal('valor_pago', 15, 2)->comment("Valor total pago (incluindo juros).");
            $table->string('sugestao_numero_nota', 10)->comment("Número da nota fiscal sugerido para correção.");
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
        Schema::dropIfExists('erros_pagamentos');
    }
};
