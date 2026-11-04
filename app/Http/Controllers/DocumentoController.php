<?php

namespace App\Http\Controllers;

use App\Models\Catalogo;
use App\Models\Documento;
use App\Models\Producto;
use App\Models\Prospecto;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Repositorio digital de documentos: expedientes, convenios, brochures y presentaciones comerciales. */
class DocumentoController extends Controller
{
    public const EXTENSIONES = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];

    private const TIPOS = [
        'sucursal' => Sucursal::class,
        'usuario' => User::class,
        'prospecto' => Prospecto::class,
        'producto' => Producto::class,
    ];

    public function index(Request $request, string $tipo, string $id): JsonResponse
    {
        $modelo = $this->resolver($tipo, $id);
        abort_unless($this->puedeVer($request->user(), $modelo), 403);

        return response()->json([
            'documentos' => $modelo->documentos()->with(['tipo:id,nombre', 'usuario:id,name,apellido_paterno'])->latest()->get()
                ->map(fn (Documento $d) => [
                    'id' => $d->id,
                    'tipo' => $d->tipo?->nombre,
                    'descripcion' => $d->descripcion,
                    'nombre' => $d->nombre_original,
                    'mime' => $d->mime,
                    'tamano' => $d->tamano,
                    'url' => route('documentos.archivo', $d),
                    'publico' => $modelo instanceof Producto ? route('documentos.publico', $d->uuid) : null,
                    'usuario' => $d->usuario?->nombre_corto,
                    'fecha' => $d->created_at->format('Y-m-d H:i'),
                ]),
            'puedeEditar' => $this->puedeEditar($request->user(), $modelo),
            'puedeBorrar' => $this->puedeBorrar($request->user(), $modelo),
            'tipos' => Catalogo::opciones(Catalogo::TIPO_DOCUMENTO),
        ]);
    }

    public function store(Request $request, string $tipo, string $id): RedirectResponse
    {
        $modelo = $this->resolver($tipo, $id);
        abort_unless($this->puedeEditar($request->user(), $modelo), 403);

        $data = $request->validate([
            // Solo PDF, documentos de Office e imágenes
            'archivo' => ['required', 'file', 'max:25600', 'mimes:'.implode(',', self::EXTENSIONES)],
            'tipo_documento_id' => ['nullable', Rule::exists('catalogos', 'id')->where('tipo', Catalogo::TIPO_DOCUMENTO)],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ], ['archivo.mimes' => 'Solo se aceptan PDF, documentos de Office (Word, Excel, PowerPoint) e imágenes.']);

        $file = $request->file('archivo');
        $modelo->documentos()->create([
            'tipo_documento_id' => $data['tipo_documento_id'] ?? null,
            'descripcion' => $data['descripcion'] ?? null,
            'archivo' => $file->store('documentos/'.$tipo.'/'.$id, 'local'),
            'nombre_original' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'tamano' => $file->getSize(),
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Documento agregado');
    }

    public function archivo(Request $request, Documento $documento): StreamedResponse
    {
        abort_unless($this->puedeVer($request->user(), $documento->documentable), 403);

        return $this->servir($documento, $request->boolean('descargar'));
    }

    /** Enlace público para compartir presentaciones de productos con clientes. */
    public function publico(string $uuid): StreamedResponse
    {
        $documento = Documento::where('uuid', $uuid)->where('documentable_type', Producto::class)->firstOrFail();

        return $this->servir($documento, false);
    }

    public function destroy(Request $request, Documento $documento): RedirectResponse
    {
        abort_unless($this->puedeBorrar($request->user(), $documento->documentable), 403);
        $documento->load('tipo');
        Storage::disk('local')->delete($documento->archivo);
        $documento->delete();

        return back()->with('success', 'Documento eliminado');
    }

    private function servir(Documento $d, bool $descargar): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($d->archivo), 404);
        $disposition = $descargar ? 'attachment' : 'inline';

        return Storage::disk('local')->response($d->archivo, $d->nombre_original, [], $disposition);
    }

    private function resolver(string $tipo, string $id): Model
    {
        abort_unless(isset(self::TIPOS[$tipo]), 404);

        return self::TIPOS[$tipo]::findOrFail($id);
    }

    private function puedeVer(User $user, ?Model $m): bool
    {
        return match (true) {
            $m instanceof Producto => true,
            $m instanceof User => $user->esAdmin() || $user->id === $m->id,
            $m instanceof Sucursal => $user->perfil !== User::PROSPECTO && in_array($m->id, $user->sucursalIds()),
            $m instanceof Prospecto => $user->perfil !== User::PROSPECTO ? in_array($m->sucursal_id, $user->sucursalIds()) : $m->user_id === $user->id,
            default => false,
        };
    }

    private function puedeEditar(User $user, Model $m): bool
    {
        return match (true) {
            $m instanceof Prospecto => $user->tienePerfil(User::ADMIN, User::SUPERVISOR, User::PROMOTOR) && in_array($m->sucursal_id, $user->sucursalIds()),
            default => $user->esAdmin() && $this->puedeVer($user, $m),
        };
    }

    private function puedeBorrar(User $user, Model $m): bool
    {
        return match (true) {
            $m instanceof Prospecto => $user->puedeProgramar() && in_array($m->sucursal_id, $user->sucursalIds()),
            default => $user->esAdmin() && $this->puedeVer($user, $m),
        };
    }
}
