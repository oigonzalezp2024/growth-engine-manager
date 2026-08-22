<?php
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
    $payloadData = json_decode($inputJSON, true);

    if (!$payloadData || (!isset($payloadData['mml_original']) && !isset($payloadData['custom_prompt']))) {
        http_response_code(400);
        echo json_encode(["error" => "Payload inválido. Se requiere 'custom_prompt' o 'mml_original'."]);
        exit;
    }

    $systemInstruction = "Eres un consultor senior experto en metodología de Marco Lógico (MML). "
        . "Tu objetivo es refactorizar y mejorar la Matriz de Marco Lógico en JSON recibida siguiendo las instrucciones recibidas. "
        . "REGLA DE ESTRUCTURA MANDATORIA: En los niveles FIN y PROPOSITO, 'resumen_narrativo' debe ser una cadena (string). "
        . "En los niveles COMPONENTES, ACTIVIDADES y ENTREGABLES, 'resumen_narrativo' DEBE ser un arreglo/array de cadenas de texto (list de strings), ordenados con prefijo (ej. C1., A1.1., E1.1.). "
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
                                        [
                                            "type" => "ARRAY", 
                                            "items" => ["type" => "STRING"]
                                        ]
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

    $promptFinal = $payloadData['custom_prompt'] ?? (
        "Refactoriza la siguiente MML aplicando observaciones y garantizando el formato de listas en 'resumen_narrativo' para COMPONENTES, ACTIVIDADES y ENTREGABLES:\n\n" .
        json_encode($payloadData['mml_original'], JSON_UNESCAPED_UNICODE)
    );

    $contents = [
        [
            "role" => "user",
            "parts" => [["text" => $promptFinal]]
        ]
    ];

    $gemini = new GeminiService(GEMINI_API_KEY, GEMINI_API_MODEL);
    $mmlOptimizadaRaw = $gemini->generateContent($contents, $systemInstruction, $jsonSchema, 0.2);

    ob_clean();
    echo json_encode([
        "status" => "success",
        "resultado_mml" => json_decode($mmlOptimizadaRaw, true)
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
