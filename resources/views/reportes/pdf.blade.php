<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $r['titulo'] }}</title>
    <style>
        @page { margin: 92px 32px 50px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #1f2a3d; }
        header { position: fixed; top: -76px; left: 0; right: 0; height: 64px; border-bottom: 3px solid #1597a5; }
        header img { height: 40px; position: absolute; left: 0; top: 6px; }
        header .tit { position: absolute; right: 0; top: 4px; text-align: right; }
        header .tit h1 { margin: 0; font-size: 17px; color: #0a1f44; }
        header .tit p { margin: 2px 0 0; font-size: 9px; color: #5d7085; }
        footer { position: fixed; bottom: -34px; left: 0; right: 0; height: 20px; font-size: 8.5px; color: #6b7a90; border-top: 1px solid #e1e8f0; padding-top: 5px; }
        footer .pag { position: absolute; right: 0; top: 5px; }
        footer .pag:after { content: "Página " counter(page); }
        .meta { margin: 0 0 10px; font-size: 9.5px; color: #44566c; }
        .meta b { color: #0a1f44; }
        table.kpis { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 12px; }
        table.kpis td { background: #f3f6fa; border-radius: 6px; padding: 7px 9px; border-left: 4px solid #0461d8; }
        table.kpis .v { font-size: 16px; font-weight: bold; }
        table.kpis .l { font-size: 8px; text-transform: uppercase; color: #5d7085; letter-spacing: .4px; }
        table.datos { width: 100%; border-collapse: collapse; }
        table.datos th { background: #0a1f44; color: #fff; text-align: left; padding: 6px 6px; font-size: 8.5px; text-transform: uppercase; letter-spacing: .3px; }
        table.datos td { padding: 5px 6px; border-bottom: 1px solid #e6ecf3; }
        table.datos tr:nth-child(even) td { background: #f7f9fc; }
        .c { text-align: center; }
        .vacio { text-align: center; padding: 30px; color: #6b7a90; font-size: 11px; }
        .chip { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 8px; font-weight: bold; color: #fff; }
    </style>
</head>
<body>
<header>
    @if ($logo)<img src="{{ $logo }}" alt="VENTRACK">@endif
    <div class="tit">
        <h1>{{ $r['titulo'] }}</h1>
        <p>{{ $r['descripcion'] }}</p>
    </div>
</header>
<footer>
    VENTRACK · Generado por {{ $r['generado_por'] }} el {{ $r['generado_en'] }}
    <span class="pag"></span>
</footer>

<p class="meta"><b>Sucursal:</b> {{ $r['sucursal'] }} &nbsp;·&nbsp; <b>Periodo:</b> {{ $r['periodo'] }} &nbsp;·&nbsp; <b>Registros:</b> {{ count($r['filas']) }}</p>

<table class="kpis">
    <tr>
        @foreach ($r['resumen'] as $k)
            <td style="border-left-color: {{ $k['color'] }}">
                <div class="v" style="color: {{ $k['color'] }}">{{ $k['valor'] }}</div>
                <div class="l">{{ $k['label'] }}</div>
            </td>
        @endforeach
    </tr>
</table>

@php
    $colores = [
        'Programada' => '#2563eb', 'En proceso' => '#d97706', 'Realizada' => '#16a34a', 'Revisada' => '#0891b2', 'Supervisada' => '#9333ea',
        'Con venta' => '#1faf5c', 'Sin venta' => '#e5484d', 'Vencida' => '#dc2626', 'Sin resultado' => '#94a3b8',
        'Comprobada' => '#16a34a', 'Pendiente' => '#94a3b8', 'Activo' => '#16a34a', 'Inactivo' => '#94a3b8',
    ];
    $conChip = ['estatus', 'resultado', 'ubicacion', 'estado'];
@endphp

<table class="datos">
    <thead>
    <tr>
        @foreach ($r['columnas'] as $c)
            <th class="{{ ($c['align'] ?? '') === 'center' ? 'c' : '' }}">{{ $c['label'] }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @forelse ($r['filas'] as $f)
        <tr>
            @foreach ($r['columnas'] as $c)
                @php $v = $f[$c['key']]; @endphp
                <td class="{{ ($c['align'] ?? '') === 'center' ? 'c' : '' }}">
                    @if (in_array($c['key'], $conChip, true) && isset($colores[$v]))
                        <span class="chip" style="background: {{ $colores[$v] }}">{{ $v }}</span>
                    @else
                        {{ $v }}
                    @endif
                </td>
            @endforeach
        </tr>
    @empty
        <tr><td colspan="{{ count($r['columnas']) }}" class="vacio">No hay información para los filtros seleccionados.</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
