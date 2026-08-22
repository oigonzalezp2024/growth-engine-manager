<?php
// Capturar errores fatales previos para evitar salida HTML que rompa el fetch
ob_start();

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../config.php';
    require_once '../GeminiService.php';

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

    $systemInstruction = "Eres un auditor experto en metodología de Marco Lógico (MML). "
        . "Tu objetivo es evaluar de manera rigurosa la coherencia cualitativa y estructural de la MML "
        . "proporcionada en formato JSON. Debes responder ÚNICAMENTE con el objeto JSON que cumpla el esquema exacto.";

    $promptUser = "Evalúa metodológicamente la siguiente Matriz de Marco Lógico en JSON:\n\n" 
        . json_encode($mmlData, JSON_UNESCAPED_UNICODE);

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

    $contents = [
        [
            "role" => "user",
            "parts" => [["text" => $promptUser]]
        ]
    ];

    $gemini = new GeminiService(GEMINI_API_KEY, GEMINI_API_MODEL);
    $evaluacionTexto = $gemini->generateContent($contents, $systemInstruction, $jsonSchema, 0.1);

    ob_clean();
    echo $evaluacionTexto;

} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
