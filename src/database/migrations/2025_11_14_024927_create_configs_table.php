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
        Schema::create('configs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')
                ->unique()
                ->constrained('empresas')
                ->onDelete('cascade');

            $table->string('conta_juros')->index();
            $table->string('conta_descontos')->index();
            $table->string('conta_pagamentos')->index();

            $table->string('codigo_historico_juros')->index();
            $table->string('codigo_historico_descontos')->index();
            $table->string('codigo_historico_pagamentos')->index();

            $table->enum('parcela_preferencial_set', ['first', 'last'])
                ->comment('Parcela preferencial para setar diferença de centavos')
                ->default('last');
            $table->enum('parcela_preferencial_get', ['first', 'last'])
                ->comment('Parcela preferencial para obter os dados do pagamento')
                ->default('last');

            $table->decimal('percentual_min_pago', 5, 2)
                ->default(85.00)
                ->comment('Percentual mínimo para considerar uma parcela como paga');
            $table->smallInteger('meses_tolerancia_desconto')
                ->default(1)
                ->comment('Número de meses de tolerância, após atingir o percentual mínimo pago, para considerar a parcela como paga com desconto');

            $table->smallInteger('meses_tolerancia_sem_pagamentos')
                ->default(6)
                ->comment('Número de meses de tolerância para considerar uma nota como sem pagamento');

            $table->boolean('parcelar')->default(true);
            $table->decimal('valor_minimo_parcela', 15, 2)->nullable()->comment('Valor mínimo para parcela');
            $table->decimal('valor_maximo_parcela', 15, 2)->nullable()->comment('Valor máximo para parcela');
            $table->smallInteger('numero_maximo_parcelas')->nullable()->comment('Número máximo de parcelas permitidas');

            $table->smallInteger('inicio_periodo_pagamento')->nullable()->comment('Início do período de pagamento');
            $table->smallInteger('fim_periodo_pagamento')->nullable()->comment('Fim do período de pagamento');

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
        Schema::dropIfExists('configs');
    }
};
