<?php

declare(strict_types=1);

class GeminiService
{
    private string $apiKey;
    private string $model;
    private int $timeout;
    private int $maxRetries;

    public function __construct(
        string $apiKey, 
        string $model = "gemini-3.6-flash", 
        int $timeout = 120, 
        int $maxRetries = 2
    ) {
        $this->apiKey = trim($apiKey);
        $this->model = ltrim(str_replace('models/', '', trim($model)), '/');
        $this->timeout = $timeout;
        $this->maxRetries = max(1, $maxRetries);
    }

    public function generateContent(
        array $contents, 
        string $systemInstruction = '', 
        ?array $jsonSchema = null, 
        float $temperature = 0.2
    ): string {
        if (empty($contents)) {
            throw new Exception("El parámetro \$contents no puede estar vacío.");
        }

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/" . rawurlencode($this->model) . ":generateContent";

        $generationConfig = [
            "temperature" => max(0.0, min(2.0, $temperature)),
        ];

        // Solo forzar JSON si se envía un esquema o si realmente se necesita respuesta estructurada
        if ($jsonSchema !== null) {
            $generationConfig["responseMimeType"] = "application/json";
            $generationConfig["responseSchema"] = $jsonSchema;
        }

        $payload = [
            "contents" => $contents,
            "generationConfig" => $generationConfig
        ];

        if ($systemInstruction !== '') {
            $payload["systemInstruction"] = [
                "parts" => [["text" => $systemInstruction]]
            ];
        }

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonPayload === false) {
            throw new Exception("Error al codificar JSON Payload: " . json_last_error_msg());
        }

        $httpCode = 0;
        $response = null;
        $lastCurlError = '';

        $ch = curl_init();

        try {
            for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
                curl_reset($ch);
                curl_setopt_array($ch, [
                    CURLOPT_URL            => $endpoint,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
                    CURLOPT_TIMEOUT        => $this->timeout,
                    CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/json',
                        'x-goog-api-key: ' . $this->apiKey // Clave segura en cabecera HTTP
                    ],
                    CURLOPT_POSTFIELDS     => $jsonPayload,
                    CURLOPT_FOLLOWLOCATION => false
                ]);

                $response = curl_exec($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if (curl_errno($ch)) {
                    $lastCurlError = curl_error($ch);
                    
                    if ($attempt < $this->maxRetries) {
                        sleep($attempt);
                        continue;
                    }
                    throw new Exception("Error cURL (intento {$attempt}/{$this->maxRetries}): " . $lastCurlError);
                }

                if ($httpCode === 200 && is_string($response)) {
                    $responseData = json_decode($response, true);
                    
                    if (!is_array($responseData)) {
                        throw new Exception("La respuesta recibida no es un JSON válido.");
                    }

                    $candidate = $responseData['candidates'][0] ?? null;
                    if (!$candidate) {
                        throw new Exception("La respuesta de la API no contiene candidatos procesables.");
                    }

                    $finishReason = $candidate['finishReason'] ?? 'STOP';
                    if ($finishReason === 'MAX_TOKENS') {
                        throw new Exception("La respuesta fue truncada por límite de tokens (MAX_TOKENS).");
                    }

                    if (!in_array($finishReason, ['STOP'], true)) {
                        throw new Exception("Generación detenida por la API. Motivo: " . $finishReason);
                    }

                    $parts = $candidate['content']['parts'] ?? [];
                    $resultText = '';

                    foreach ($parts as $part) {
                        // Ignorar razonamiento del modelo
                        if (!empty($part['thought'])) {
                            continue;
                        }
                        if (isset($part['text']) && is_string($part['text'])) {
                            $resultText .= $part['text'];
                        }
                    }

                    $resultText = trim($resultText);

                    // Limpieza robusta de delimitadores Markdown (```json o ```)
                    if (preg_match('/^\s*```(?:json)?\s*(.*?)\s*```\s*$/is', $resultText, $matches)) {
                        $resultText = trim($matches[1]);
                    }

                    if ($resultText === '') {
                        throw new Exception("Estructura de respuesta incompleta: no se encontró contenido de texto.");
                    }

                    return $resultText;
                }

                if (in_array($httpCode, [429, 502, 503, 504], true) && $attempt < $this->maxRetries) {
                    sleep($attempt * 2);
                } else {
                    break;
                }
            }
        } finally {
            if ($ch instanceof CurlHandle || is_resource($ch)) {
                curl_close($ch);
            }
        }

        $errorMsg = (string) $response;
        $parsedError = json_decode((string) $response, true);
        if (isset($parsedError['error']['message']) && is_string($parsedError['error']['message'])) {
            $errorMsg = $parsedError['error']['message'];
        }

        throw new Exception("Error API Gemini (HTTP {$httpCode}): " . $errorMsg);
    }
}
