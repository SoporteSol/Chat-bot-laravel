<?php

namespace App\Services;

use App\Models\Instancia;
use App\Models\UserContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CotizacionService
{
    /**
     * Genera los datos normalizados para una cotización formal.
     */
    public function prepararDatos(Instancia $instancia, UserContext $lead, array $opciones = []): array
    {
        $productos = is_array($lead->productos_cotizados) ? $lead->productos_cotizados : [];
        $subtotal = 0;
        $items = [];

        foreach ($productos as $p) {
            $cant = max(1, (int) ($p['cantidad'] ?? 1));
            $precio = (float) ($p['precio'] ?? 0);
            $totalItem = $cant * $precio;
            $subtotal += $totalItem;
            $items[] = [
                'nombre'     => $p['nombre'] ?? 'Producto / Servicio',
                'sku'        => $p['sku'] ?? $p['producto_id'] ?? '—',
                'cantidad'   => $cant,
                'precio'     => $precio,
                'total_item' => $totalItem,
            ];
        }

        $datosFiscales = is_array($instancia->datos_fiscales) ? $instancia->datos_fiscales : [];
        $moneda = $datosFiscales['moneda'] ?? 'USD';
        $porcentajeImpuesto = (float) ($opciones['porcentaje_impuesto'] ?? $datosFiscales['porcentaje_impuesto'] ?? 0);
        $impuesto = round($subtotal * ($porcentajeImpuesto / 100), 2);
        $total = $subtotal + $impuesto;

        $folio = $opciones['folio'] ?? ('COT-' . str_pad((string) $lead->id, 4, '0', STR_PAD_LEFT) . '-' . date('ymd'));
        $fecha = $opciones['fecha'] ?? date('d/m/Y');
        $validezDias = (int) ($opciones['validez_dias'] ?? 15);

        return [
            'folio'               => $folio,
            'fecha'               => $fecha,
            'validez_dias'        => $validezDias,
            'moneda'              => $moneda,
            'empresa'             => [
                'nombre'        => $instancia->nombre_empresa,
                'telefono'      => $instancia->telefono,
                'razon_social'  => $datosFiscales['razon_social'] ?? $instancia->nombre_empresa,
                'nit_rfc'       => $datosFiscales['nit_rfc'] ?? '—',
                'direccion'     => $datosFiscales['direccion'] ?? '—',
                'email'         => $datosFiscales['email_contacto'] ?? '—',
                'logo_url'      => $datosFiscales['logo_url'] ?? null,
            ],
            'cliente'             => [
                'nombre'   => $lead->nombre,
                'empresa'  => $lead->empresa_cliente,
                'telefono' => $lead->phone_number,
                'email'    => $lead->email,
            ],
            'items'               => $items,
            'subtotal'            => $subtotal,
            'porcentaje_impuesto' => $porcentajeImpuesto,
            'impuesto'            => $impuesto,
            'total'               => $total,
            'notas'               => $opciones['notas'] ?? $datosFiscales['terminos_condiciones'] ?? 'Esta cotización tiene una validez de ' . $validezDias . ' días calendario. Precios sujetos a disponibilidad.',
        ];
    }

    /**
     * Genera la instancia PDF de Dompdf lista para renderizar o guardar.
     */
    public function renderPdf(array $datos): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('cotizaciones.pdf', $datos)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont'          => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => true,
            ]);
    }

    /**
     * Guarda el archivo PDF en disco público y lo registra en el historial del lead.
     */
    public function guardarYRegistrar(Instancia $instancia, UserContext $lead, array $opciones = []): array
    {
        $datos = $this->prepararDatos($instancia, $lead, $opciones);
        $pdf = $this->renderPdf($datos);

        $filename = 'cotizaciones/' . Str::slug($instancia->nombre_empresa) . '_' . $datos['folio'] . '.pdf';
        Storage::disk('public')->put($filename, $pdf->output());
        $urlPublica = asset('storage/' . $filename);

        // Registrar en historial del lead
        $historial = is_array($lead->cotizaciones_historial) ? $lead->cotizaciones_historial : [];
        $registro = [
            'id'       => uniqid('cot_', true),
            'folio'    => $datos['folio'],
            'total'    => $datos['total'],
            'moneda'   => $datos['moneda'],
            'url'      => $urlPublica,
            'archivo'  => $filename,
            'fecha'    => now()->format('Y-m-d H:i:s'),
        ];
        $historial[] = $registro;
        $lead->cotizaciones_historial = $historial;

        // Registrar actividad en timeline
        $lead->registrarActividad(
            'cotizacion',
            'Se generó la cotización formal ' . $datos['folio'] . ' por un valor de ' . $datos['moneda'] . ' $' . number_format($datos['total'], 2),
            auth()->user()?->name ?? 'Asesor',
            ['folio' => $datos['folio'], 'url' => $urlPublica, 'total' => $datos['total']]
        );

        return [
            'datos'       => $datos,
            'url'         => $urlPublica,
            'path_rel'    => $filename,
            'registro'    => $registro,
        ];
    }
}

