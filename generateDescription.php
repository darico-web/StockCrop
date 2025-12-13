<?php
// Set the content type to JSON for the AJAX response
header('Content-Type: application/json');

// --- 1. Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

if (!isset($_POST['productName']) || !isset($_POST['category'])) {
    http_response_code(400); 
    echo json_encode(['error' => 'Product name and category are required']);
    exit;
}

$productName = trim($_POST['productName']);
$category = trim($_POST['category']); 

// --- 2. Configuration & Prompt ---
$model = 'gemini-2.5-flash';
<<<<<<< HEAD
//Gemini API Key !!!
$apiKey = 'AIzaSyAbssvcn6p33GC6u224uv3LMKVUeijeM6s'; 
=======
//Gemini API Key
$apiKey = 'gemini api key goes here'; 
>>>>>>> 8409061d2de25e7c7aea8ea202f0fc7acd47f869
// NOTE: A more detailed prompt helps avoid safety blocks
$prompt = "You are a professional, concise product catalog writer. Write a factual and objective 3-4 sentence (maximum 400 words) description for the item '$productName' in the fresh produce category '$category'. Focus only on the fresh quality, great taste, and local origin.";

$endpoint = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=$apiKey";

// --- 3. CORRECT Request Body Structure for Gemini API ---
$data = [
    // 'contents' must be an array of Content objects
    'contents' => [
        [
            // The actual prompt text is in 'parts'
            'parts' => [
                ['text' => $prompt]
            ]
        ]
    ],
    // Generation settings are under 'generationConfig'
    'generationConfig' => [
        'temperature' => 0.7,
        'maxOutputTokens' => 1000
    ]
];

// --- 4. cURL Execution ---
$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
// WARNING: The insecure fix. Remove this line if you've updated php.ini with cacert.pem.
//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    // cURL error (network failure, timeout, SSL issue)
    http_response_code(500);
    echo json_encode(['error' => 'cURL Connection Error: ' . $err]);
    exit;
}

$responseData = json_decode($response, true);

// --- 5. CORRECT Response Parsing and Error Handling ---
// The correct path to the text is candidates[0] -> content -> parts[0] -> text
if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
    $description = $responseData['candidates'][0]['content']['parts'][0]['text'];
    echo json_encode(['description' => $description]);
} 
// Handle explicit API errors (e.g., invalid key, rate limit)
else if (isset($responseData['error'])) {
    http_response_code($responseData['error']['code'] ?? 500); 
    echo json_encode(['error' => 'Gemini API Error: ' . $responseData['error']['message']]);
}
// Handle safety blocks or unknown response structure
else {
    // Check if the failure was due to SAFETY
    $reason = $responseData['candidates'][0]['finishReason'] ?? 'UNKNOWN_REASON';
    $message = ($reason === 'SAFETY') 
        ? 'Description blocked by safety filters. Try rephrasing the product name.' 
        : 'Unknown API response structure. Finish Reason: ' . $reason;
        
    http_response_code(422); // Unprocessable Entity is suitable for safety/logic blocks
    echo json_encode(['error' => $message]);
}
