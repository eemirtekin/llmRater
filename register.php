<?php

$REGISTER_LTI2 = array(
    "name" => "LLM Rater",
    "FontAwesome" => "fa-robot",
    "short_name" => "LLMRater",
    "description" => "An advanced LTI tool that leverages the Gemini API to evaluate open-ended student responses. Features include customizable evaluation criteria, markdown support, batch evaluation capabilities, and detailed feedback generation.",
    "messages" => array(
        "launch",
        "launch_grade"
    ),
    "privacy_level" => "name_only",
    "license" => "Apache",
    "languages" => array(
        "English",
        "Turkish"
    ),
    "analytics" => array(
        "enabled" => true,
        "tracking" => array(
            "student_submissions",
            "evaluation_metrics",
            "instructor_feedback"
        )
    ),
    "features" => array(
        "markdown_support",
        "batch_evaluation",
        "custom_rubrics",
        "export_functionality",
        "attempt_limiting"
    ),
    "requirements" => array(
        "gemini_api_key" => true,
        "php_version" => ">=7.4"
    ),
    "source_url" => "https://github.com/tsugitools/llmrater",
    "issues_url" => "https://github.com/tsugitools/llmrater/issues",
    "documentation_url" => "https://github.com/tsugitools/llmrater/wiki",
    "placements" => array(
        "course_navigation",
        "homework_submission",
        "editor_button"
    ),
    "screen_shots" => array(
        "store/screen-01.png",
        "store/screen-02.png",
        "store/screen-03.png"
    ),
    // Improved support for course copy
    "value_added" => array(
        "course_copy_support" => true,
        "rubric_association" => true,
        "gradebook_integration" => true
    )
);