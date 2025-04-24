<?php
namespace LLMRater;

class GeminiRater {
    private $apiKey;
    private $model = 'gemini-2.0-flash';
    private $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    public function evaluate($question, $answer, $prompt) {
        $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";
        
        // Construct the evaluation prompt
        $systemPrompt = "You are an educational assessment assistant. Your task is to evaluate a student's answer based on the given criteria.\n\n";
        $context = "Question: {$question}\n\nStudent Answer: {$answer}\n\nEvaluation Criteria:\n{$prompt}\n\n";
        $task = "Please evaluate the student's answer based on the given criteria. Provide a structured response with scoring and detailed feedback.";

        $data = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $systemPrompt . $context . $task]
                    ]
                ]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        // Get HTTP response code
        curl_setopt($ch, CURLOPT_HEADER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $headerSize);
        
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("API Error: " . $error);
        }

        if ($httpCode !== 200) {
            throw new \Exception("API returned error code: " . $httpCode);
        }

        $result = json_decode($body, true);
        return $this->parseResponse($result);
    }

    private function parseResponse($result) {
        if (!$result) {
            throw new \Exception("Empty response from API");
        }

        if (isset($result['error'])) {
            throw new \Exception("API Error: " . $result['error']['message'] ?? 'Unknown error');
        }

        if (!isset($result['candidates']) || empty($result['candidates'])) {
            throw new \Exception("No response candidates returned from API");
        }

        if (!isset($result['candidates'][0]['content']) || !isset($result['candidates'][0]['content']['parts'])) {
            throw new \Exception("Invalid response format: missing content or parts");
        }

        if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception("Invalid API response format: missing text");
        }

        return [
            'raw_response' => $result['candidates'][0]['content']['parts'][0]['text'],
            'timestamp' => time()
        ];
    }
}