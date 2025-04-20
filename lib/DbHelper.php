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
            question TEXT NOT NULL,
            prompt TEXT NOT NULL,
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
    }

    public function saveQuestion($linkId, $question, $prompt) {
        $sql = "INSERT INTO {$this->p}llm_questions 
                (link_id, question, prompt) 
                VALUES (:lid, :q, :p)
                ON DUPLICATE KEY UPDATE
                question = VALUES(question),
                prompt = VALUES(prompt)";
        
        $values = array(
            ':lid' => $linkId,
            ':q' => $question,
            ':p' => $prompt
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
        $sql = "SELECT r.*, u.displayname, u.email 
                FROM {$this->p}llm_responses r
                JOIN {$this->p}lti_user u ON r.user_id = u.user_id
                WHERE r.question_id = :qid
                ORDER BY r.submitted_at DESC";
        
        return $this->PDOX->allRowsDie($sql, array(':qid' => $questionId));
    }

    public function saveEvaluation($responseId, $evaluationText) {
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

    public function getEvaluation($responseId) {
        $sql = "SELECT * FROM {$this->p}llm_evaluations 
                WHERE response_id = :rid 
                ORDER BY evaluated_at DESC LIMIT 1";
        
        return $this->PDOX->rowDie($sql, array(':rid' => $responseId));
    }

    public function getResponseDetails($responseId) {
        $sql = "SELECT r.*, q.question, q.prompt, e.evaluation_text, e.evaluated_at,
                       u.displayname, u.email
                FROM {$this->p}llm_responses r
                JOIN {$this->p}llm_questions q ON r.question_id = q.question_id
                JOIN {$this->p}lti_user u ON r.user_id = u.user_id
                LEFT JOIN {$this->p}llm_evaluations e ON r.response_id = e.response_id
                WHERE r.response_id = :rid";
        
        return $this->PDOX->rowDie($sql, array(':rid' => $responseId));
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
}