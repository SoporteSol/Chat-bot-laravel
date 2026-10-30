<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización {{ $folio }} — {{ $empresa['nombre'] }}</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
        }
        body {
            font-size: 12px;
            line-height: 1.5;
            padding: 24px;
            margin: 0;
            background: #ffffff;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .company-title {
            font-size: 20px;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 4px 0;
        }
        .company-subtitle {
            font-size: 11px;
            color: #64748b;
        }
        .folio-box {
            text-align: right;
        }
        .folio-title {
            font-size: 18px;
            font-weight: bold;
            color: #2563eb;
            margin: 0;
        }
        .folio-date {
            font-size: 11px;
            color: #475569;
            margin-top: 4px;
        }
        .parties-table {
            width: 100%;
            margin-bottom: 24px;
        }
        .party-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px;
            vertical-align: top;
            width: 48%;
        }
        .party-header {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .party-name {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .party-detail {
            font-size: 11px;
            color: #475569;
            margin-bottom: 2px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .items-table th {
            background: #0f172a;
            color: #ffffff;
            font-size: 11px;
            font-weight: 600;
            text-align: left;
            padding: 8px 10px;
        }
        .items-table td {
            padding: 9px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
        }
        .items-table tr:nth-child(even) td {
            background: #f8fafc;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals-table {
            width: 100%;
            margin-bottom: 30px;
        }
        .totals-box {
            width: 320px;
            float: right;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #f8fafc;
            padding: 10px 14px;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin-bottom: 4px;
        }
        .totals-total {
            border-top: 2px solid #cbd5e1;
            padding-top: 6px;
            margin-top: 6px;
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
        }
        .terms-box {
            clear: both;
            border-top: 1px dashed #cbd5e1;
            padding-top: 14px;
            font-size: 10px;
            color: #64748b;
        }
        .terms-title {
            font-weight: bold;
            color: #334155;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .footer-note {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td>
                <h1 class="company-title">{{ $empresa['nombre'] }}</h1>
                <div class="company-subtitle">{{ $empresa['razon_social'] }} &bull; NIT/RFC: {{ $empresa['nit_rfc'] }}</div>
                <div class="company-subtitle">{{ $empresa['direccion'] }} &bull; Tel: +{{ $empresa['telefono'] }}</div>
            </td>
            <td class="folio-box">
                <div class="folio-title">COTIZACIÓN</div>
                <div class="folio-date"><strong>Folio:</strong> {{ $folio }}</div>
                <div class="folio-date"><strong>Fecha:</strong> {{ $fecha }}</div>
                <div class="folio-date"><strong>Validez:</strong> {{ $validez_dias }} días</div>
            </td>
        </tr>
    </table>

    <table class="parties-table">
        <tr>
            <td class="party-card">
                <div class="party-header">Emitido Por:</div>
                <div class="party-name">{{ $empresa['nombre'] }}</div>
                <div class="party-detail"><strong>Email:</strong> {{ $empresa['email'] }}</div>
                <div class="party-detail"><strong>WhatsApp:</strong> +{{ $empresa['telefono'] }}</div>
                <div class="party-detail"><strong>Razón Social:</strong> {{ $empresa['razon_social'] }}</div>
            </td>
            <td style="width: 4%;"></td>
            <td class="party-card">
                <div class="party-header">Cotizado A:</div>
                <div class="party-name">{{ $cliente['nombre'] }}</div>
                @if($cliente['empresa'])
                    <div class="party-detail"><strong>Empresa:</strong> {{ $cliente['empresa'] }}</div>
                @endif
                <div class="party-detail"><strong>WhatsApp:</strong> +{{ $cliente['telefono'] }}</div>
                @if($cliente['email'])
                    <div class="party-detail"><strong>Email:</strong> {{ $cliente['email'] }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 45%;">Descripción del Producto o Servicio</th>
                <th style="width: 15%;">SKU</th>
                <th style="width: 12%;" class="text-center">Cantidad</th>
                <th style="width: 14%;" class="text-right">Precio Unitario</th>
                <th style="width: 14%;" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $it)
                <tr>
                    <td><strong>{{ $it['nombre'] }}</strong></td>
                    <td style="color:#64748b;">{{ $it['sku'] }}</td>
                    <td class="text-center">{{ $it['cantidad'] }}</td>
                    <td class="text-right">${{ number_format($it['precio'], 2) }}</td>
                    <td class="text-right" style="font-weight:bold;">${{ number_format($it['total_item'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center" style="padding: 18px; color: #94a3b8;">Sin ítems registrados en la cotización.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td style="width: 55%; vertical-align: top;"></td>
            <td style="width: 45%;">
                <div class="totals-box">
                    <table style="width: 100%;">
                        <tr>
                            <td style="color:#64748b;">Subtotal:</td>
                            <td class="text-right" style="font-weight:600;">${{ number_format($subtotal, 2) }} {{ $moneda }}</td>
                        </tr>
                        @if($porcentajeImpuesto > 0)
                            <tr>
                                <td style="color:#64748b;">Impuestos ({{ $porcentajeImpuesto }}%):</td>
                                <td class="text-right" style="font-weight:600;">${{ number_format($impuesto, 2) }} {{ $moneda }}</td>
                            </tr>
                        @endif
                        <tr style="border-top: 1px solid #cbd5e1;">
                            <td style="font-size: 13px; font-weight: bold; padding-top: 6px; color:#0f172a;">TOTAL:</td>
                            <td class="text-right" style="font-size: 14px; font-weight: bold; padding-top: 6px; color:#10b981;">
                                ${{ number_format($total, 2) }} {{ $moneda }}
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="terms-box">
        <div class="terms-title">Términos y Condiciones</div>
        <div>{{ $notas }}</div>
    </div>

    <div class="footer-note">
        Generado formalmente a través del sistema CRM Conversacional de {{ $empresa['nombre'] }}.
    </div>
</body>
</html>

