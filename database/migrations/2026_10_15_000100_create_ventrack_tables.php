<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogos dinámicos: tipos de negocio, tipos de documento y formatos de observación
        Schema::create('catalogos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 30)->index(); // tipo_negocio | tipo_documento | formato_observacion
            $table->string('nombre');
            $table->text('nota')->nullable();
            $table->text('plantilla')->nullable(); // texto prediseñado (solo formatos)
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['tipo', 'nombre']);
        });

        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->foreignId('tipo_negocio_id')->nullable()->constrained('catalogos')->nullOnDelete();
            $table->text('descripcion')->nullable(); // giro / descripción del negocio
            $table->string('direccion');
            $table->string('colonia')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('estado')->nullable();
            $table->string('codigo_postal', 10)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('sitio_web')->nullable();
            $table->string('facebook')->nullable();
            $table->string('instagram')->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('otras_redes')->nullable();
            $table->foreignId('responsable_id')->constrained('users');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('sucursal_user', function (Blueprint $table) {
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['sucursal_id', 'user_id']);
        });

        Schema::create('prospectos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_persona', 10)->default('Moral'); // Física | Moral
            $table->string('nombre'); // nombre o razón social
            $table->string('nombre_comercial')->nullable();
            $table->string('rfc', 13)->nullable();
            $table->string('contacto')->nullable(); // representante / persona de contacto
            $table->string('telefono', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('direccion');
            $table->string('colonia')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('codigo_postal', 10)->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->foreignId('tipo_negocio_id')->nullable()->constrained('catalogos')->nullOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // acceso al portal
            $table->text('notas')->nullable();
            $table->timestamps();
        });

        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('tipo', 10)->default('Servicio'); // Producto | Servicio
            $table->string('area'); // tipo, área o departamento
            $table->decimal('costo', 12, 2)->default(0);
            $table->string('unidad')->nullable(); // por mes, por visita, pieza...
            $table->text('descripcion')->nullable();
            $table->text('informacion')->nullable();
            $table->json('links')->nullable(); // [{titulo, url}]
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('producto_sucursal', function (Blueprint $table) {
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->primary(['producto_id', 'sucursal_id']);
        });

        Schema::create('visitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospecto_id')->constrained('prospectos');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('promotor_id')->constrained('users');
            $table->foreignId('programada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha')->index();
            $table->time('hora')->nullable();
            $table->string('objetivo')->nullable();
            $table->string('estatus', 20)->default('programada')->index();
            $table->string('resultado', 10)->nullable(); // positiva | negativa
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->timestamp('ubicacion_at')->nullable();
            $table->timestamp('iniciada_at')->nullable();
            $table->timestamp('realizada_at')->nullable();
            $table->timestamp('revisada_at')->nullable();
            $table->timestamp('supervisada_at')->nullable();
            $table->timestamps();
        });

        Schema::create('visita_observaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visita_id')->constrained('visitas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('estatus', 20); // estatus de la visita al momento de anotar
            $table->foreignId('formato_id')->nullable()->constrained('catalogos')->nullOnDelete();
            $table->text('texto');
            $table->timestamps();
        });

        Schema::create('visita_evidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visita_id')->constrained('visitas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('estatus', 20);
            $table->string('tipo', 12); // foto | ubicacion
            $table->string('archivo')->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->decimal('precision', 10, 2)->nullable();
            $table->timestamps();
        });

        // Repositorio de documentos (sucursales, usuarios, prospectos, presentaciones de productos)
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->morphs('documentable');
            $table->foreignId('tipo_documento_id')->nullable()->constrained('catalogos')->nullOnDelete();
            $table->string('descripcion')->nullable();
            $table->string('archivo');
            $table->string('nombre_original');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('tamano')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospecto_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('promotor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('from_id')->constrained('users')->cascadeOnDelete();
            $table->text('texto');
            $table->timestamp('leido_at')->nullable();
            $table->timestamps();
            $table->index(['prospecto_user_id', 'promotor_id']);
        });

        // Trazabilidad global: cada alta, cambio o baja
        Schema::create('sys_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tabla_afectada', 60)->index();
            $table->unsignedBigInteger('registro_id')->nullable()->index();
            $table->string('accion', 10); // INSERT | UPDATE | DELETE
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha')->index();
            $table->time('hora');
            $table->string('descripcion')->nullable();
            $table->json('cambios_json')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['sys_logs', 'mensajes', 'documentos', 'visita_evidencias', 'visita_observaciones', 'visitas',
            'producto_sucursal', 'productos', 'prospectos', 'sucursal_user', 'sucursales', 'catalogos'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
