<?php
/**
 * Integración con Meta Graph API (Insights)
 * - Control de Licencia y Configuración Inicial bajo Asistencia Técnica
 * - Autenticación mediante Header Bearer
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

define('GRAPH_VERSION', 'v19.0');
define('DEFAULT_TIMEOUT', 15);
define('META_ACT_ACCOUNT_ID', 'act_META_ACT_ACCOUNT_ID_AQUI');
define('META_ACCESS_TOKEN', 'META_ACCESS_TOKEN_AQUI');

// Obtención de credenciales desde el entorno del servidor (Configurado por Soporte Técnico)
$accessToken  = META_ACCESS_TOKEN ?: null;
$actAccountId = META_ACT_ACCOUNT_ID ?: null;

// Validación de Activación Inicial (Modelo de Negocio)
if (!$accessToken || !$actAccountId || $accessToken === 'TU_ACCESS_TOKEN_AQUI') {
    http_response_code(403); // Forbidden: Requiere activación
    echo json_encode([
        'status'  => 'unlicensed',
        'message' => 'Instancia no configurada. Contacte a soporte técnico para la activación del servicio y configuración del entorno.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Parámetros de la consulta de Insights
$fields = [
    'ad_id',
    'ad_name',
    'campaign_id',
    'campaign_name',
    'spend',
    'impressions',
    'clicks',
    'ctr',
    'actions',
    'cost_per_action_type'
];

function callMetaGraphApi(string $url, string $token): array {
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => DEFAULT_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$token}",
            "Accept: application/json"
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return [
            'success' => false,
            'code'    => 500,
            'error'   => "Error de conexión cURL: {$curlError}"
        ];
    }

    $decoded = json_decode($response, true);

    if ($httpCode >= 400 || isset($decoded['error'])) {
        return [
            'success' => false,
            'code'    => $httpCode,
            'error'   => $decoded['error']['message'] ?? 'Error de autenticación con la API de Meta.'
        ];
    }

    return [
        'success' => true,
        'code'    => $httpCode,
        'data'    => $decoded
    ];
}

function fetchAllAdInsights(string $account, string $version, string $token, array $fields): array {
    $queryParams = http_build_query([
        'level'       => 'ad',
        'fields'      => implode(',', $fields),
        'date_preset' => 'this_month',
        'limit'       => 100
    ]);

    $nextUrl = "https://graph.facebook.com/{$version}/{$account}/insights?{$queryParams}";
    $allInsights = [];

    while ($nextUrl) {
        $result = callMetaGraphApi($nextUrl, $token);

        if (!$result['success']) {
            return [
                'status'  => 'error',
                'message' => $result['error']
            ];
        }

        $payload = $result['data'];
        
        if (!empty($payload['data'])) {
            $allInsights = array_merge($allInsights, $payload['data']);
        }

        $nextUrl = $payload['paging']['next'] ?? null;
    }

    return [
        'status' => 'success',
        'count'  => count($allInsights),
        'data'   => $allInsights
    ];
}

try {
    $response = fetchAllAdInsights($actAccountId, GRAPH_VERSION, $accessToken, $fields);
    http_response_code($response['status'] === 'error' ? 400 : 200);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Error interno en la ejecución del servidor.'
    ], JSON_UNESCAPED_UNICODE);
}
