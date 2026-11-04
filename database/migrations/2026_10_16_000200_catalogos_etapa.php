<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Etapa del proceso de la visita en la que se precarga cada formato de observación. */
    public function up(): void
    {
        Schema::table('catalogos', function (Blueprint $table) {
            $table->string('etapa', 20)->nullable()->after('plantilla');
        });

        $formatos = DB::table('catalogos')->where('tipo', 'formato_observacion');
        foreach (['Primera visita' => 'programada', 'Cierre de venta' => 'proceso', 'Revisión de auditoría' => 'realizada'] as $nombre => $etapa) {
            (clone $formatos)->where('nombre', $nombre)->update(['etapa' => $etapa]);
        }
        if ((clone $formatos)->exists() && ! (clone $formatos)->where('nombre', 'Supervisión')->exists()) {
            DB::table('catalogos')->insert([
                'tipo' => 'formato_observacion', 'nombre' => 'Supervisión', 'nota' => 'Visto bueno final del supervisor',
                'plantilla' => "Proceso completo (sí/no):\nCalidad de la evidencia:\nAcuerdos finales:", 'etapa' => 'revisada',
                'activo' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('catalogos', function (Blueprint $table) {
            $table->dropColumn('etapa');
        });
    }
};
