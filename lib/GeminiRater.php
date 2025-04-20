<?php
namespace LLMRater;

class GeminiRater {
    private $apiKey;
    private $model = 'gemini-pro';
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

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("API Error: " . $error);
        }

        $result = json_decode($response, true);
        return $this->parseResponse($result);
    }

    private function parseResponse($result) {
        if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception("Invalid API response format");
        }

        return [
            'raw_response' => $result['candidates'][0]['content']['parts'][0]['text'],
            'timestamp' => time()
        ];
    }
}