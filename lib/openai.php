<?php
namespace LLMRater;

class OpenAI {
    private $apiKey;
    private $model = 'gpt-4o-mini';
    private $baseUrl = 'https://api.openai.com/v1';

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    public function evaluate($question, $answer, $prompt, $additionalPrompt = null) {
        $url = "{$this->baseUrl}/chat/completions";
        
        // Build the prompt with clear sections
        $systemPrompt = "You are an educational assessment assistant evaluating a student's answer.\n\n";
        $context = sprintf(
            "Question:\n%s\n\nStudent Answer:\n%s\n\nEvaluation Criteria:\n%s%s",
            trim($question),
            trim($answer),
            trim($prompt),
            $additionalPrompt ? "\n\nAdditional Instructions:\n" . trim($additionalPrompt) : ""
        );
        
        $task = "\n\nProvide a structured evaluation with clear scoring and detailed feedback.";

        $data = [
            "model" => $this->model,
            "messages" => [
                ["role" => "system", "content" => $systemPrompt],
                ["role" => "user", "content" => $context . $task]
            ],
            "temperature" => 0.7,
            "max_tokens" => 1024
        ];

        // Set up curl with connection timeout
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("API Connection Error: " . $error);
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMessage = isset($errorData['error']['message']) ? 
                $errorData['error']['message'] : 
                "API returned error code: " . $httpCode;
            throw new \Exception($errorMessage);
        }

        return $this->parseResponse(json_decode($response, true));
    }

    public function evaluateBatch($responses, $batchSize = 5) {
        $results = [];
        $batches = array_chunk($responses, $batchSize);
        
        foreach ($batches as $batch) {
            foreach ($batch as $response) {
                try {
                    $result = $this->evaluate(
                        $response['question'],
                        $response['answer'],
                        $response['prompt'],
                        $response['additional_prompt'] ?? null
                    );
                    $results[$response['response_id']] = $result;
                } catch (\Exception $e) {
                    error_log(sprintf(
                        "Error evaluating response %d: %s",
                        $response['response_id'],
                        $e->getMessage()
                    ));
                    continue;
                }
                // Add a small delay between requests to avoid rate limiting
                usleep(200000); // 200ms delay
            }
        }
        
        return $results;
    }

    private function parseResponse($result) {
        if (!$result) {
            throw new \Exception("Empty response from API");
        }

        if (isset($result['error'])) {
            throw new \Exception("API Error: " . ($result['error']['message'] ?? 'Unknown error'));
        }

        if (!isset($result['choices'][0]['message']['content'])) {
            throw new \Exception("Invalid API response format: missing content");
        }

        return [
            'raw_response' => $result['choices'][0]['message']['content'],
            'timestamp' => time(),
            'model' => $this->model
        ];
    }
}