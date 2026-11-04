<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Adjuntos (fotos, PDF u Office) ligados al comentario con el que se subieron. */
    public function up(): void
    {
        Schema::table('visita_evidencias', function (Blueprint $table) {
            $table->foreignId('visita_observacion_id')->nullable()->after('user_id')->constrained('visita_observaciones')->nullOnDelete();
            $table->string('nombre')->nullable()->after('archivo');
        });
    }

    public function down(): void
    {
        Schema::table('visita_evidencias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('visita_observacion_id');
            $table->dropColumn('nombre');
        });
    }
};
