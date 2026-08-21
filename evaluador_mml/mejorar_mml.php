<?php
header('Content-Type: application/json; charset=utf-8');

require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido. Utilice POST."]);
    exit;
}

$inputJSON = file_get_contents('php://input');
$payloadData = json_decode($inputJSON, true);

if (!$payloadData || (!isset($payloadData['mml_original']) && !isset($payloadData['custom_prompt']))) {
    http_response_code(400);
    echo json_encode(["error" => "Payload inválido. Se requiere 'custom_prompt' o 'mml_original'."]);
    exit;
}

$apiKey = GEMINI_API_KEY; 

function mejorarMatrizMarcoLogico(string $promptUser, string $apiKey): string 
{
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

    $systemInstruction = "Eres un consultor senior experto en metodología de Marco Lógico (MML). "
        . "Tu objetivo es refactorizar y mejorar la Matriz de Marco Lógico en JSON recibida siguiendo las instrucciones recibidas. "
        . "DEBES responder ÚNICAMENTE con el objeto JSON que cumpla el esquema estricto de producción.";

    $jsonSchema = [
        "type" => "OBJECT",
        "properties" => [
            "matriz_marco_logico" => [
                "type" => "OBJECT",
                "properties" => [
                    "titulo" => ["type" => "STRING"],
                    "periodo_ejecucion" => ["type" => "STRING"],
                    "niveles" => [
                        "type" => "ARRAY",
                        "items" => [
                            "type" => "OBJECT",
                            "properties" => [
                                "nivel" => ["type" => "STRING"],
                                "resumen_narrativo" => [
                                    "oneOf" => [
                                        ["type" => "STRING"],
                                        ["type" => "ARRAY", "items" => ["type" => "STRING"]]
                                    ]
                                ],
                                "indicadores_verificables" => [
                                    "type" => "ARRAY",
                                    "items" => ["type" => "STRING"]
                                ],
                                "medios_de_verificacion" => [
                                    "type" => "ARRAY",
                                    "items" => ["type" => "STRING"]
                                ],
                                "supuestos" => [
                                    "type" => "ARRAY",
                                    "items" => ["type" => "STRING"]
                                ]
                            ],
                            "required" => ["nivel", "resumen_narrativo", "indicadores_verificables", "medios_de_verificacion", "supuestos"]
                        ]
                    ]
                ],
                "required" => ["titulo", "periodo_ejecucion", "niveles"]
            ]
        ],
        "required" => ["matriz_marco_logico"]
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
            "temperature" => 0.2
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
    $promptFinal = $payloadData['custom_prompt'] ?? (
        "Refactoriza la siguiente MML aplicando observaciones:\n\n" .
        json_encode($payloadData['mml_original'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    $mmlOptimizadaRaw = mejorarMatrizMarcoLogico($promptFinal, $apiKey);
    $mmlOptimizada = json_decode($mmlOptimizadaRaw, true);

    echo json_encode([
        "status" => "success",
        "resultado_mml" => $mmlOptimizada
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
