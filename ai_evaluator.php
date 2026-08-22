<?php
/**
 * Módulo de Evaluación Estratégica con IA (Gemini API)
 * - Procesa exclusivamente MML + Métricas de Meta Ads.
 * - Genera diagnósticos y sugerencias de ajuste a la Matriz de Marco Lógico.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once './config.php';
require_once './GeminiService.php';

// 1. Configurar la API Key
$apiKeyEnv = GEMINI_API_KEY;

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

try {
    // 4. Prompt de Sistema
    $systemPrompt = "Eres un Auditor Estratégico de Marketing Digital y Proyectos. Analiza la Matriz de Marco Lógico (MML) actual y los datos de rendimiento publicitario brindados por Meta Ads.\n\n" .
    "Tu tarea:\n" .
    "1. Evaluar si las Actividades y Componentes actuales se están alineando con los datos reales de Meta.\n" .
    "2. Identificar desviaciones o deficiencias operativas.\n" .
    "3. Sugerir correcciones inmediatas a las actividades de ejecución.\n" .
    "4. Generar una versión mejorada/modificada de la sección \"niveles\" de la Matriz de Marco Lógico (MML) para cumplir con los objetivos.\n\n" .
    "DEBES responder ÚNICAMENTE en formato JSON válido.";

    // 5. Esquema para Structured Outputs
    $jsonSchema = [
        "type" => "OBJECT",
        "properties" => [
            "evaluacion_general" => ["type" => "STRING"],
            "hallazgos" => [
                "type" => "ARRAY",
                "items" => ["type" => "STRING"]
            ],
            "actividades_sugeridas" => [
                "type" => "ARRAY",
                "items" => ["type" => "STRING"]
            ],
            "nueva_mml" => [
                "type" => "OBJECT",
                "properties" => [
                    "niveles" => [
                        "type" => "ARRAY",
                        "items" => ["type" => "OBJECT"]
                    ]
                ],
                "required" => ["niveles"]
            ]
        ],
        "required" => ["evaluacion_general", "hallazgos", "actividades_sugeridas", "nueva_mml"]
    ];

    $contents = [
        [
            'role' => 'user',
            'parts' => [
                [
                    'text' => "DATOS MML:\n" . json_encode($mml, JSON_UNESCAPED_UNICODE) . "\n\nMETRICAS META ADS:\n" . json_encode($metaInsights, JSON_UNESCAPED_UNICODE)
                ]
            ]
        ]
    ];

    // 6. Instancia y ejecución del servicio
    $gemini = new GeminiService(GEMINI_API_KEY, "gemini-2.5-flash");
    $aiContentRaw = $gemini->generateContent($contents, $systemPrompt, $jsonSchema, 0.2);

    unset($contents, $mml, $metaInsights);

    $aiContentParsed = json_decode($aiContentRaw, true);

    echo json_encode([
        'status' => 'success',
        'analysis' => $aiContentParsed
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
