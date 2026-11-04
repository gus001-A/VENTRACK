<?php

namespace App\Http\Controllers;

use App\Models\SysLog;
use App\Models\User;
use App\Models\Visita;
use App\Support\Reportes;
use App\Support\SucursalActual;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Reportes en pantalla, PDF y Excel (respetan perfil, sucursal seleccionada y filtros). */
class ReporteController extends Controller
{
    private const FILTROS = ['desde', 'hasta', 'promotor', 'estatus', 'resultado', 'q', 'vencidas'];

    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        $f = $this->filtros($request);
        $tipo = array_key_exists($request->input('tipo'), Reportes::TIPOS) ? $request->input('tipo') : 'visitas';
        $sucursal = SucursalActual::resolver($request);
        $reporte = (new Reportes($user, $sucursal, $f))->generar($tipo);

        return Inertia::render('Reportes/Index', [
            'tipo' => $tipo,
            'tipos' => Reportes::TIPOS,
            'filtros' => $f,
            // Vista previa: primeras 100 filas
            'reporte' => [...$reporte, 'total' => count($reporte['filas']), 'filas' => array_slice($reporte['filas'], 0, 100)],
            'promotores' => $user->perfil === User::PROMOTOR ? [] : User::perfil(User::PROMOTOR)
                ->when($sucursal, fn ($q, $v) => $q->whereHas('sucursales', fn ($s) => $s->where('sucursales.id', $v)))
                ->orderBy('name')->get(['id', 'name', 'apellido_paterno'])->map(fn ($u) => ['id' => $u->id, 'nombre' => $u->nombre_corto]),
            'estatus' => Visita::ETIQUETAS,
        ]);
    }

    public function exportar(Request $request, string $tipo, string $formato): Response
    {
        abort_unless(array_key_exists($tipo, Reportes::TIPOS), 404);
        $reporte = (new Reportes($request->user(), SucursalActual::resolver($request), $this->filtros($request)))->generar($tipo);
        $archivo = Str::slug($reporte['titulo'].' '.$reporte['sucursal']).'-'.now()->format('Ymd-Hi');

        SysLog::registrar('reportes', null, 'EXPORT', $reporte['titulo'].' ('.strtoupper($formato === 'excel' ? 'xlsx' : 'pdf').') · '.$reporte['sucursal'].' · '.$reporte['periodo']);

        return $formato === 'pdf' ? $this->pdf($reporte, $archivo) : $this->excel($reporte, $archivo);
    }

    private function filtros(Request $request): array
    {
        $request->validate([
            'desde' => ['nullable', 'date', 'before_or_equal:today'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ], ['desde.before_or_equal' => 'La fecha "desde" no puede ser mayor a hoy.', 'hasta.after_or_equal' => 'La fecha "hasta" no puede ser anterior a "desde".']);

        return array_filter($request->only(self::FILTROS), fn ($v) => $v !== null && $v !== '');
    }

    private function pdf(array $reporte, string $archivo): Response
    {
        $logo = public_path('images/ventrack-logo.png');

        return Pdf::loadView('reportes.pdf', [
            'r' => $reporte,
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logo)) : null,
        ])->setPaper('letter', 'landscape')->download($archivo.'.pdf');
    }

    private function excel(array $r, string $archivo): StreamedResponse
    {
        $libro = new Spreadsheet;
        $libro->getProperties()->setCreator('VENTRACK')->setTitle($r['titulo']);
        $hoja = $libro->getActiveSheet()->setTitle(Str::limit($r['titulo'], 28, ''));
        $cols = $r['columnas'];
        $ultima = chr(ord('A') + count($cols) - 1);

        // Encabezado con logo
        $logo = public_path('images/ventrack-logo.png');
        if (is_file($logo)) {
            (new Drawing)->setPath($logo)->setHeight(38)->setCoordinates('A1')->setWorksheet($hoja);
        }
        $hoja->getRowDimension(1)->setRowHeight(32);
        $hoja->getRowDimension(2)->setRowHeight(10);
        $hoja->setCellValue('C1', $r['titulo']);
        $hoja->getStyle('C1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('0A1F44');
        $hoja->setCellValue('A3', 'Sucursal: '.$r['sucursal'].'   ·   Periodo: '.$r['periodo'].'   ·   Generado por '.$r['generado_por'].' el '.$r['generado_en']);
        $hoja->mergeCells("A3:{$ultima}3");
        $hoja->getStyle('A3')->getFont()->setSize(10)->getColor()->setRGB('5D7085');

        // Resumen
        $fila = 5;
        foreach ($r['resumen'] as $i => $k) {
            $col = chr(ord('A') + $i);
            if ($col > $ultima) {
                break;
            }
            $hoja->setCellValue("{$col}{$fila}", $k['label']);
            $hoja->setCellValue($col.($fila + 1), $k['valor']);
            $hoja->getStyle("{$col}{$fila}")->getFont()->setSize(9)->setBold(true)->getColor()->setRGB('5D7085');
            $hoja->getStyle($col.($fila + 1))->getFont()->setSize(14)->setBold(true)->getColor()->setRGB(ltrim($k['color'], '#'));
            $hoja->getStyle("{$col}{$fila}:{$col}".($fila + 1))->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F6FA']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        // Tabla
        $ini = $fila + 3;
        foreach ($cols as $i => $c) {
            $col = chr(ord('A') + $i);
            $hoja->setCellValue("{$col}{$ini}", $c['label']);
            $hoja->getColumnDimension($col)->setWidth($c['ancho'] ?? 15);
        }
        $hoja->getStyle("A{$ini}:{$ultima}{$ini}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0A1F44']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $hoja->getRowDimension($ini)->setRowHeight(22);

        $n = $ini;
        foreach ($r['filas'] as $f) {
            $n++;
            foreach ($cols as $i => $c) {
                $hoja->setCellValue(chr(ord('A') + $i).$n, $f[$c['key']]);
            }
            if (($n - $ini) % 2 === 0) {
                $hoja->getStyle("A{$n}:{$ultima}{$n}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F6F9FC');
            }
        }
        if ($n > $ini) {
            $hoja->getStyle("A{$ini}:{$ultima}{$n}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E1E8F0');
            $hoja->setAutoFilter("A{$ini}:{$ultima}{$n}");
        }
        foreach ($cols as $i => $c) {
            if (($c['align'] ?? null) === 'center') {
                $col = chr(ord('A') + $i);
                $hoja->getStyle("{$col}{$ini}:{$col}{$n}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }
        $hoja->freezePane('A'.($ini + 1));
        $hoja->setCellValue('A'.($n + 2), 'Total de registros: '.count($r['filas']));
        $hoja->getStyle('A'.($n + 2))->getFont()->setBold(true);
        $hoja->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);

        return response()->streamDownload(fn () => (new Xlsx($libro))->save('php://output'), $archivo.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
