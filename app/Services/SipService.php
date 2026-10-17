<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SipService
{
    private string $baseUrl;

    private string $apiKeyServicio;

    public function __construct()
    {
        $this->baseUrl = config('sip.base_api_url');
        $this->apiKeyServicio = config('sip.apikey_servicio');
    }

    /**
     * Obtiene un token de autenticación contra el API de SIP.
     */
    public function generarToken(): array
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post(config('sip.base_auth_url'), [
            'username' => config('sip.username'),
            'password' => config('sip.password'),
        ]);

        if ($response->failed()) {
            Log::error('SIP: Error al generar token', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('No se pudo generar el token de pago.');
        }

        return $response->json();
    }

    /**
     * Genera un código QR de cobro para una suscripción.
     */
    public function generaQr(string $alias, string $detalleGlosa, float $monto, string $token): array
    {
        $payload = [
            'alias' => $alias,
            'callback' => config('sip.callback_url'),
            'detalleGlosa' => substr($detalleGlosa, 0, 30),
            'monto' => number_format($monto, 2, '.', ''),
            'moneda' => config('sip.moneda'),
            'fechaVencimiento' => now()->addDay()->format('d/m/Y'),
            'tipoSolicitud' => config('sip.tipo_solicitud'),
        ];

        Log::info('SIP: Generando código QR', $payload);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$token,
            'apikey' => $this->apiKeyServicio,
        ])->post($this->baseUrl.'generaQr', $payload);

        if ($response->failed()) {
            Log::error('SIP: Error al generar QR', [
                'alias' => $alias,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('No se pudo generar el código QR.');
        }

        return $response->json();
    }

    /**
     * Consulta el estado actual de una transacción por su alias.
     */
    public function estadoTransaccion(string $alias, string $token): array
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$token,
            'apikey' => $this->apiKeyServicio,
        ])->post($this->baseUrl.'estadoTransaccion', [
            'alias' => $alias,
        ]);

        if ($response->failed()) {
            Log::error('SIP: Error al consultar estado', [
                'alias' => $alias,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('No se pudo consultar el estado de la transacción.');
        }

        return $response->json();
    }
}
