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
        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_conciliada_id')
                ->constrained('contas_conciliadas')
                ->onDelete('cascade');

            $table->date('data')->comment("Data do pagamento.");
            $table->string('doc', 15)->comment("Documento do pagamento.");
            $table->unsignedSmallInteger('parcela')->comment("Número da parcela.");
            $table->string('numero_nota', 10)->nullable()->comment("Número da nota fiscal associada ao pagamento.");
            $table->decimal('valor_nota', 15, 2)->nullable()->comment("Valor da nota fiscal associada ao pagamento.");
            $table->decimal('valor_pago', 15, 2)->comment("Valor total pago (incluindo juros).");
            $table->decimal('valor_juros', 15, 2)->nullable()->comment("Valor dos juros aplicados no pagamento.");
            $table->decimal('valor_descontos', 15, 2)->nullable()->comment("Valor dos descontos aplicados no pagamento.");
            $table->string('code', 10)->nullable()->comment("");
            $table->enum('tipo', [
                'anterior',
                'nota não encontrada',
                'com juros',
                'com descontos',
                'parcialmente pago',
                'pago com nota'
            ])->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('deleted_at')->nullable();

            $table->unique(['conta_conciliada_id', 'doc']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagamentos');
    }
};
