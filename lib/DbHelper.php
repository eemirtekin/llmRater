<?php
namespace LLMRater;

class DbHelper {
    private $PDOX;
    private $p; // Database prefix

    public function __construct($PDOX, $p) {
        $this->PDOX = $PDOX;
        $this->p = $p;
    }

    public function createTables() {
        // Questions table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->p}llm_questions (
            question_id INT AUTO_INCREMENT PRIMARY KEY,
            link_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            question TEXT NOT NULL,
            prompt TEXT NOT NULL,
            attempt_limit INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (link_id) REFERENCES {$this->p}lti_link(link_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->PDOX->queryDie($sql);

        // Student responses table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->p}llm_responses (
            response_id INT AUTO_INCREMENT PRIMARY KEY,
            question_id INT NOT NULL,
            user_id INT NOT NULL,
            answer TEXT NOT NULL,
            submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (question_id) REFERENCES {$this->p}llm_questions(question_id),
            FOREIGN KEY (user_id) REFERENCES {$this->p}lti_user(user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->PDOX->queryDie($sql);

        // LLM evaluations table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->p}llm_evaluations (
            evaluation_id INT AUTO_INCREMENT PRIMARY KEY,
            response_id INT NOT NULL,
            evaluation_text TEXT NOT NULL,
            evaluated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (response_id) REFERENCES {$this->p}llm_responses(response_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->PDOX->queryDie($sql);

        // Run migrations after creating tables
        $this->migrateDatabase();
    }

    public function migrateDatabase() {
        // Check if title column exists in llm_questions table
        $sql = "SELECT COUNT(*) as count 
                FROM information_schema.columns 
                WHERE table_schema = DATABASE()
                AND table_name = '{$this->p}llm_questions' 
                AND column_name = 'title'";
        
        $result = $this->PDOX->rowDie($sql);
        
        if ($result['count'] == 0) {
            // Add title column if it doesn't exist
            $sql = "ALTER TABLE {$this->p}llm_questions 
                    ADD COLUMN title VARCHAR(255) DEFAULT 'Untitled Question' NOT NULL AFTER link_id";
            $this->PDOX->queryDie($sql);

            // Set default titles for existing questions
            $sql = "UPDATE {$this->p}llm_questions 
                    SET title = CONCAT('Question #', question_id)";
            $this->PDOX->queryDie($sql);
        }

        // Check if attempt_limit column exists
        $sql = "SELECT COUNT(*) as count 
                FROM information_schema.columns 
                WHERE table_schema = DATABASE()
                AND table_name = '{$this->p}llm_questions' 
                AND column_name = 'attempt_limit'";
        
        $result = $this->PDOX->rowDie($sql);
        
        if ($result['count'] == 0) {
            // Add attempt_limit column if it doesn't exist
            $sql = "ALTER TABLE {$this->p}llm_questions 
                    ADD COLUMN attempt_limit INT DEFAULT NULL AFTER prompt";
            $this->PDOX->queryDie($sql);
        }
    }

    public function saveQuestion($linkId, $title, $question, $prompt, $attemptLimit = null) {
        $sql = "INSERT INTO {$this->p}llm_questions 
                (link_id, title, question, prompt, attempt_limit) 
                VALUES (:lid, :title, :q, :p, :limit)";
        
        $values = array(
            ':lid' => $linkId,
            ':title' => $title,
            ':q' => $question,
            ':p' => $prompt,
            ':limit' => $attemptLimit
        );
        
        $this->PDOX->queryDie($sql, $values);
        return $this->PDOX->lastInsertId();
    }

    public function getQuestion($linkId) {
        $sql = "SELECT * FROM {$this->p}llm_questions 
                WHERE link_id = :lid 
                ORDER BY question_id DESC LIMIT 1";
        
        return $this->PDOX->rowDie($sql, array(':lid' => $linkId));
    }

    public function getAllQuestions($linkId) {
        $sql = "SELECT * FROM {$this->p}llm_questions 
                WHERE link_id = :lid 
                ORDER BY created_at DESC";
        
        return $this->PDOX->allRowsDie($sql, array(':lid' => $linkId));
    }

    public function getQuestionById($questionId) {
        $sql = "SELECT * FROM {$this->p}llm_questions 
                WHERE question_id = :qid";
        
        return $this->PDOX->rowDie($sql, array(':qid' => $questionId));
    }

    public function saveResponse($questionId, $userId, $answer) {
        $sql = "INSERT INTO {$this->p}llm_responses 
                (question_id, user_id, answer) 
                VALUES (:qid, :uid, :ans)";
        
        $values = array(
            ':qid' => $questionId,
            ':uid' => $userId,
            ':ans' => $answer
        );
        
        $this->PDOX->queryDie($sql, $values);
        return $this->PDOX->lastInsertId();
    }

    public function getResponses($questionId) {
        $sql = "SELECT r.*, 
                       u.displayname, u.email, u.user_id,
                       p.profile_id, p.displayname AS profile_displayname, p.email AS profile_email,
                       p.json AS profile_json
                FROM {$this->p}llm_responses r
                JOIN {$this->p}lti_user u ON r.user_id = u.user_id
                LEFT JOIN {$this->p}profile p ON u.profile_id = p.profile_id
                WHERE r.question_id = :qid
                ORDER BY r.submitted_at DESC";
        
        $responses = $this->PDOX->allRowsDie($sql, array(':qid' => $questionId));
        
        // Process the responses to ensure displayname is set correctly
        foreach ($responses as &$response) {
            // If displayname is empty, try to use profile_displayname
            if (empty($response['displayname']) && !empty($response['profile_displayname'])) {
                $response['displayname'] = $response['profile_displayname'];
            }
            
            // If still empty, check if we have JSON profile data
            if (empty($response['displayname']) && !empty($response['profile_json'])) {
                $profile = json_decode($response['profile_json'], true);
                if (!empty($profile['name'])) {
                    $response['displayname'] = $profile['name'];
                }
            }
            
            // Fallback to email if all else fails
            if (empty($response['displayname']) && !empty($response['email'])) {
                // Use email but remove @domain.com part
                $response['displayname'] = strstr($response['email'], '@', true);
            }
            
            // If still nothing, use a generic name
            if (empty($response['displayname'])) {
                $response['displayname'] = 'Student ' . $response['user_id'];
            }
        }
        
        return $responses;
    }

    public function saveEvaluation($responseId, $evaluationText) {
        // First check if an evaluation exists
        $existingEvaluation = $this->getEvaluation($responseId);
        
        if ($existingEvaluation) {
            // Update existing evaluation
            $sql = "UPDATE {$this->p}llm_evaluations 
                    SET evaluation_text = :eval,
                        evaluated_at = CURRENT_TIMESTAMP
                    WHERE evaluation_id = :eid";
            
            $values = array(
                ':eval' => $evaluationText,
                ':eid' => $existingEvaluation['evaluation_id']
            );
            
            $this->PDOX->queryDie($sql, $values);
            return $existingEvaluation['evaluation_id'];
        } else {
            // Insert new evaluation
            $sql = "INSERT INTO {$this->p}llm_evaluations 
                    (response_id, evaluation_text) 
                    VALUES (:rid, :eval)";
            
            $values = array(
                ':rid' => $responseId,
                ':eval' => $evaluationText
            );
            
            $this->PDOX->queryDie($sql, $values);
            return $this->PDOX->lastInsertId();
        }
    }

    public function getEvaluation($responseId) {
        $sql = "SELECT * FROM {$this->p}llm_evaluations 
                WHERE response_id = :rid 
                ORDER BY evaluated_at DESC LIMIT 1";
        
        return $this->PDOX->rowDie($sql, array(':rid' => $responseId));
    }

    public function getResponseDetails($response_id) {
        $stmt = $this->PDOX->queryDie(
            "SELECT r.*, u.displayname, q.question, q.prompt, q.title, e.evaluation_text, e.evaluated_at 
            FROM {$this->p}llm_responses r
            JOIN {$this->p}lti_user u ON r.user_id = u.user_id
            JOIN {$this->p}llm_questions q ON r.question_id = q.question_id
            LEFT JOIN {$this->p}llm_evaluations e ON r.response_id = e.response_id
            WHERE r.response_id = :rid",
            array(':rid' => $response_id)
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            $row['question'] = strip_tags($row['question']);
        }
        return $row;
    }

    public function getAllResponsesForExport($questionId) {
        $sql = "SELECT r.*, q.question, q.prompt, e.evaluation_text, e.evaluated_at,
                       u.displayname, u.email
                FROM {$this->p}llm_responses r
                JOIN {$this->p}llm_questions q ON r.question_id = q.question_id
                JOIN {$this->p}lti_user u ON r.user_id = u.user_id
                LEFT JOIN {$this->p}llm_evaluations e ON r.response_id = e.response_id
                WHERE q.question_id = :qid
                ORDER BY r.submitted_at DESC";
        
        return $this->PDOX->allRowsDie($sql, array(':qid' => $questionId));
    }

    public function updateQuestion($questionId, $title, $question, $prompt, $attemptLimit = null) {
        $sql = "UPDATE {$this->p}llm_questions 
                SET question = :q, prompt = :p, title = :title, attempt_limit = :limit 
                WHERE question_id = :qid";
        
        $values = array(
            ':qid' => $questionId,
            ':q' => $question,
            ':p' => $prompt,
            ':title' => $title,
            ':limit' => $attemptLimit
        );
        
        return $this->PDOX->queryDie($sql, $values);
    }

    public function deleteQuestion($questionId) {
        // First delete all evaluations related to responses for this question
        $sql = "DELETE e FROM {$this->p}llm_evaluations e
                INNER JOIN {$this->p}llm_responses r ON e.response_id = r.response_id
                WHERE r.question_id = :qid";
        $this->PDOX->queryDie($sql, array(':qid' => $questionId));
        
        // Then delete all responses for this question
        $sql = "DELETE FROM {$this->p}llm_responses WHERE question_id = :qid";
        $this->PDOX->queryDie($sql, array(':qid' => $questionId));
        
        // Finally delete the question itself
        $sql = "DELETE FROM {$this->p}llm_questions WHERE question_id = :qid";
        return $this->PDOX->queryDie($sql, array(':qid' => $questionId));
    }

    public function getAttemptCount($questionId, $userId) {
        $sql = "SELECT COUNT(*) as count 
                FROM {$this->p}llm_responses 
                WHERE question_id = :qid AND user_id = :uid";
        
        $result = $this->PDOX->rowDie($sql, array(
            ':qid' => $questionId,
            ':uid' => $userId
        ));
        
        return $result['count'];
    }

    public function canSubmitResponse($questionId, $userId) {
        $question = $this->getQuestionById($questionId);
        if (!$question) {
            return false;
        }
        
        // If no attempt limit is set, always allow submission
        if ($question['attempt_limit'] === null) {
            return true;
        }
        
        $attempts = $this->getAttemptCount($questionId, $userId);
        return $attempts < $question['attempt_limit'];
    }

    public function getAdjacentResponses($responseId) {
        // Get the current response's question_id and submitted_at
        $sql = "SELECT question_id, submitted_at FROM {$this->p}llm_responses WHERE response_id = :rid";
        $current = $this->PDOX->rowDie($sql, array(':rid' => $responseId));
        
        if (!$current) {
            return null;
        }

        // Get previous response
        $sql = "SELECT response_id as prev_id FROM {$this->p}llm_responses 
                WHERE question_id = :qid 
                AND submitted_at > :submitted 
                ORDER BY submitted_at ASC LIMIT 1";
        $prev = $this->PDOX->rowDie($sql, array(
            ':qid' => $current['question_id'],
            ':submitted' => $current['submitted_at']
        ));

        // Get next response
        $sql = "SELECT response_id as next_id FROM {$this->p}llm_responses 
                WHERE question_id = :qid 
                AND submitted_at < :submitted 
                ORDER BY submitted_at DESC LIMIT 1";
        $next = $this->PDOX->rowDie($sql, array(
            ':qid' => $current['question_id'],
            ':submitted' => $current['submitted_at']
        ));

        return array(
            'prev_id' => $prev ? $prev['prev_id'] : null,
            'next_id' => $next ? $next['next_id'] : null
        );
    }
}