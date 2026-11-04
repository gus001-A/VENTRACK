<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Autocompletado de dirección por código postal (México).
 * Colonias y estado: Zippopotam; municipio: COPOMEX (si hay token) u OpenStreetMap.
 * Las respuestas se guardan en caché para no repetir consultas.
 */
class CodigoPostalController extends Controller
{
    public function __invoke(string $cp): JsonResponse
    {
        abort_unless(preg_match('/^\d{5}$/', $cp), 422, 'Código postal inválido');

        $data = Cache::get("cp:$cp");
        if (! $data) {
            $data = $this->consultar($cp);
            if ($data['colonias'] || $data['estado']) {
                Cache::forever("cp:$cp", $data);
            }
        }

        return response()->json($data);
    }

    private function consultar(string $cp): array
    {
        $res = ['cp' => $cp, 'estado' => null, 'municipio' => null, 'colonias' => []];

        if ($token = config('services.copomex.token')) {
            $r = rescue(fn () => Http::timeout(8)->get("https://api.copomex.com/query/info_cp/$cp", ['token' => $token])->json(), null, false);
            if (is_array($r) && ! ($r[0]['error'] ?? true)) {
                $res['estado'] = $r[0]['response']['estado'] ?? null;
                $res['municipio'] = $r[0]['response']['municipio'] ?? null;
                $res['colonias'] = collect($r)->pluck('response.asentamiento')->filter()->unique()->sort()->values()->all();

                return $res;
            }
        }

        $z = rescue(fn () => Http::timeout(8)->get("https://api.zippopotam.us/mx/$cp")->json(), null, false);
        if (is_array($z) && ! empty($z['places'])) {
            $res['estado'] = $z['places'][0]['state'] ?? null;
            $res['colonias'] = collect($z['places'])->pluck('place name')->unique()->sort()->values()->all();
        }

        $n = rescue(fn () => Http::timeout(8)->withHeaders(['User-Agent' => config('app.name').'/1.0'])
            ->get('https://nominatim.openstreetmap.org/search', [
                'postalcode' => $cp, 'country' => 'mx', 'format' => 'json', 'addressdetails' => 1, 'limit' => 1, 'accept-language' => 'es',
            ])->json(), null, false);
        if (is_array($n) && ! empty($n[0]['address'])) {
            $a = $n[0]['address'];
            $res['municipio'] = $a['county'] ?? $a['borough'] ?? $a['city'] ?? $a['town'] ?? null;
            $res['estado'] ??= $a['state'] ?? null;
        }

        return $res;
    }
}
