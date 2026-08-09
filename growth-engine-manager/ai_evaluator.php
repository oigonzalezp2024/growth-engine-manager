<?php
/**
 * Módulo de Evaluación Estratégica con IA (Gemini API)
 * - Procesa exclusivamente MML + Métricas de Meta Ads.
 * - Genera diagnósticos y sugerencias de ajuste a la Matriz de Marco Lógico.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// 1. Configurar la API Key
$apiKeyEnv = getenv('GEMINI_API_KEY');
define('GEMINI_API_KEY', $apiKeyEnv ? $apiKeyEnv : 'GEMINI_API_KEY_AQUI'); // <--- Coloca tu API Key real aquí

define('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent');

// 2. Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Lectura del Input JSON
$input = json_decode(file_get_contents('php://input'), true);

$mml = $input['mml'] ?? null;
$metaInsights = $input['meta_insights'] ?? null;

if (!$mml || !$metaInsights) {
    http_response_code(400);
    echo json_encode(['error' => 'Se requiere la MML y las métricas de Meta para la evaluación.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!GEMINI_API_KEY || GEMINI_API_KEY === 'TU_API_KEY_AQUI') {
    http_response_code(500);
    echo json_encode(['error' => 'GEMINI_API_KEY no está configurada correctamente.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Prompt de Sistema (concatenación estándar para evitar errores de sintaxis en PHP)
$systemPrompt = "Eres un Auditor Estratégico de Marketing Digital y Proyectos. Analiza la Matriz de Marco Lógico (MML) actual y los datos de rendimiento publicitario brindados por Meta Ads.\n\n" .
"Tu tarea:\n" .
"1. Evaluar si las Actividades y Componentes actuales se están alineando con los datos reales de Meta.\n" .
"2. Identificar desviaciones o deficiencias operativas.\n" .
"3. Sugerir correcciones inmediatas a las actividades de ejecución.\n" .
"4. Generar una versión mejorada/modificada de la sección \"niveles\" de la Matriz de Marco Lógico (MML) para cumplir con los objetivos.\n\n" .
"DEBES responder ÚNICAMENTE en formato JSON válido estructurado exactamente así:\n" .
"{\n" .
"  \"evaluacion_general\": \"Resumen ejecutivo del desempeño según los datos de Meta\",\n" .
"  \"hallazgos\": [\"Punto 1\", \"Punto 2\"],\n" .
"  \"actividades_sugeridas\": [\"Sugerencia 1\", \"Sugerencia 2\"],\n" .
"  \"nueva_mml\": {\n" .
"    \"niveles\": [ ... MML con las correcciones o mejoras propuestas ... ]\n" .
"  }\n" .
"}";

// 5. Estructura de Payload
$payload = [
    'systemInstruction' => [
        'parts' => [
            ['text' => $systemPrompt]
        ]
    ],
    'contents' => [
        [
            'role' => 'user',
            'parts' => [
                [
                    'text' => "DATOS MML:\n" . json_encode($mml, JSON_UNESCAPED_UNICODE) . "\n\nMETRICAS META ADS:\n" . json_encode($metaInsights, JSON_UNESCAPED_UNICODE)
                ]
            ]
        ]
    ],
    'generationConfig' => [
        'responseMimeType' => 'application/json',
        'temperature' => 0.2
    ]
];

$url = GEMINI_ENDPOINT . '?key=' . GEMINI_API_KEY;

// 6. Configurar y Ejecutar cURL
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 45,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE)
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// 7. Validación de Errores cURL / API
if ($curlError) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión cURL: ' . $curlError], JSON_UNESCAPED_UNICODE);
    exit;
}

$decoded = json_decode($response, true);

if ($httpCode >= 400) {
    http_response_code($httpCode);
    echo json_encode(['error' => 'Error desde la API de Gemini', 'details' => $decoded], JSON_UNESCAPED_UNICODE);
    exit;
}

// 8. Extracción y parseo de la respuesta
$aiContentRaw = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;

if (!$aiContentRaw) {
    http_response_code(500);
    echo json_encode(['error' => 'La API de Gemini no devolvió contenido.', 'raw' => $decoded], JSON_UNESCAPED_UNICODE);
    exit;
}

// Convertir la respuesta de la IA de String JSON a un Array PHP
$aiContentParsed = json_decode($aiContentRaw, true);

if ($aiContentParsed === null) {
    $cleanJson = preg_replace('/^```json\s*|\s*```$/i', '', trim($aiContentRaw));
    $aiContentParsed = json_decode($cleanJson, true) ?? $aiContentRaw;
}

// Respuesta exitosa al cliente
echo json_encode([
    'status' => 'success',
    'analysis' => $aiContentParsed
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
