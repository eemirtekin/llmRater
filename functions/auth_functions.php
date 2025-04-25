<?php
namespace LLMRater\Functions;

class Auth {
    /**
     * Validates instructor role and returns user data
     */
    public static function requireInstructor($LAUNCH) {
        if (!$LAUNCH->user->instructor) {
            die('Instructor role required');
        }
        return $LAUNCH->user;
    }

    /**
     * Redirects with session message
     */
    public static function redirectWithMessage($url, $message, $type = 'success') {
        $_SESSION[$type] = $message;
        header('Location: ' . addSession($url));
        exit();
    }

    /**
     * Validates API keys for LLM services
     */
    public static function validateApiKeys($settings) {
        $errors = [];
        
        if (!$settings->get('gemini_api_key')) {
            $errors[] = 'Gemini API key not configured';
        }
        
        if (!$settings->get('openai_api_key')) {
            $errors[] = 'OpenAI API key not configured';
        }
        
        return $errors;
    }
}