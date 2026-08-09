<?php
// Configuración de la API de Gemini
$apiKey = "GEMINI_API_KEY_AQUI"; // Reemplaza esto con tu API Key real
$model = "gemini-2.5-flash"; // Puedes usar gemini-1.5-pro u otro modelo disponible
$url = "https://generativelanguage.googleapis.com/v1beta/models/" . $model . ":generateContent?key=" . $apiKey;

// Datos que se enviarán a la API
$data = [
    "contents" => [
        [
            "parts" => [
                [
                    "text" => "Hola, responde capital de colombia."
                ]
            ]
        ]
    ]
];

// Codificar los datos en formato JSON
$payload = json_encode($data);

// Inicializar cURL
$ch = curl_init($url);

// Configurar las opciones de cURL
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

// Opcional: Desactivar la verificación SSL solo si estás en entorno local de pruebas (XAMPP/WAMP) y tienes problemas de certificados
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

// Ejecutar la solicitud
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

// Cerrar la sesión cURL
curl_close($ch);

// Validar resultados
if ($error) {
    echo "<h3>Error de conexión cURL:</h3>";
    echo "<p>" . htmlspecialchars($error) . "</p>";
} else {
    echo "<h3>Código HTTP de respuesta: " . $httpCode . "</h3>";
    echo "<h3>Respuesta de la API:</h3>";
    echo "<pre>";
    
    // Decodificar y mostrar de forma bonita el JSON recibido
    $responseData = json_decode($response, true);
    if ($httpCode === 200 && isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        echo "<strong>Mensaje de Gemini: </strong>" . htmlspecialchars($responseData['candidates'][0]['content']['parts'][0]['text']);
    } else {
        // Si hay un error de la API (ej. API Key inválida), mostramos el JSON completo
        print_r($responseData);
    }
    
    echo "</pre>";
}
