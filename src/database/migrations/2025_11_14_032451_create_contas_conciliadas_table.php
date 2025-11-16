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
        Schema::create('contas_conciliadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conciliacao_id')
                ->constrained('conciliacoes')
                ->onDelete('cascade');
            $table->string('numero', 10)->index();
            $table->string('nome', 100)->nullable()->index();
            $table->string('mascara_contabil', 15)->nullable();

            $table->boolean('balanceado')->default(false);

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
        Schema::dropIfExists('contas_conciliadas');
    }
};
