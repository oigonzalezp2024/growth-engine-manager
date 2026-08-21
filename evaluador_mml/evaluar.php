<?php
header('Content-Type: application/json; charset=utf-8');

require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido. Utilice POST."]);
    exit;
}

$inputJSON = file_get_contents('php://input');
$mmlData = json_decode($inputJSON, true);

if (!$mmlData || !isset($mmlData['matriz_marco_logico'])) {
    http_response_code(400);
    echo json_encode(["error" => "JSON de entrada inválido o sin estructura 'matriz_marco_logico'."]);
    exit;
}

$apiKey = GEMINI_API_KEY; 

function evaluarMatrizMarcoLogico(array $mmlData, string $apiKey): string 
{
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

    $systemInstruction = "Eres un auditor experto en metodología de Marco Lógico (MML). "
        . "Tu objetivo es evaluar de manera rigurosa la coherencia cualitativa y structural de la MML "
        . "proporcionada en formato JSON. Debes responder ÚNICAMENTE con el objeto JSON que cumpla el esquema exacto.";

    $promptUser = "Evalúa metodológicamente la siguiente Matriz de Marco Lógico en JSON:\n\n" 
        . json_encode($mmlData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    $jsonSchema = [
        "type" => "OBJECT",
        "properties" => [
            "evaluacion_metodologica" => [
                "type" => "OBJECT",
                "properties" => [
                    "cumplimiento_general" => ["type" => "STRING"],
                    "estado_validacion" => ["type" => "STRING"],
                    "matriz_de_alineacion" => [
                        "type" => "ARRAY",
                        "items" => [
                            "type" => "OBJECT",
                            "properties" => [
                                "nivel" => ["type" => "STRING"],
                                "objetivo_teorico" => ["type" => "STRING"],
                                "cumplimiento_json" => ["type" => "STRING"],
                                "observacion" => ["type" => "STRING"]
                            ],
                            "required" => ["nivel", "objetivo_teorico", "cumplimiento_json", "observacion"]
                        ]
                    ],
                    "ejes_de_control" => [
                        "type" => "OBJECT",
                        "properties" => [
                            "indicadores_verificables" => [
                                "type" => "OBJECT",
                                "properties" => [
                                    "coherencia" => ["type" => "STRING"],
                                    "detalle" => ["type" => "STRING"]
                                ],
                                "required" => ["coherencia", "detalle"]
                            ],
                            "medios_de_verificacion" => [
                                "type" => "OBJECT",
                                "properties" => [
                                    "coherencia" => ["type" => "STRING"],
                                    "detalle" => ["type" => "STRING"]
                                ],
                                "required" => ["coherencia", "detalle"]
                            ],
                            "supuestos" => [
                                "type" => "OBJECT",
                                "properties" => [
                                    "coherencia" => ["type" => "STRING"],
                                    "detalle" => ["type" => "STRING"]
                                ],
                                "required" => ["coherencia", "detalle"]
                            ]
                        ],
                        "required" => ["indicadores_verificables", "medios_de_verificacion", "supuestos"]
                    ],
                    "veredicto_final" => ["type" => "STRING"]
                ],
                "required" => [
                    "cumplimiento_general",
                    "estado_validacion",
                    "matriz_de_alineacion",
                    "ejes_de_control",
                    "veredicto_final"
                ]
            ]
        ],
        "required" => ["evaluacion_metodologica"]
    ];

    $payload = [
        "system_instruction" => [
            "parts" => [["text" => $systemInstruction]]
        ],
        "contents" => [
            ["parts" => [["text" => $promptUser]]]
        ],
        "generationConfig" => [
            "response_mime_type" => "application/json",
            "response_schema" => $jsonSchema,
            "temperature" => 0.1
        ]
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $errorMsg = curl_error($ch);
        curl_close($ch);
        throw new Exception("Error cURL: " . $errorMsg);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Error API Gemini (HTTP {$httpCode}): " . $response);
    }

    $responseData = json_decode($response, true);
    return $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
}

try {
    $evaluacionJSON = evaluarMatrizMarcoLogico($mmlData, $apiKey);
    echo $evaluacionJSON;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
