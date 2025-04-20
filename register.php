<?php

$REGISTER_LTI2 = array(
"name" => "LLM Rater",
"FontAwesome" => "fa-robot",
"short_name" => "LLMRater",
"description" => "An LTI tool that uses Gemini API to evaluate open-ended student responses. Instructors can create questions with evaluation prompts, and students can submit their answers through a text editor.",
    "messages" => array("launch", "launch_grade"),
    "privacy_level" => "name_only",  // anonymous, name_only, public
    "license" => "Apache",
    "languages" => array(
        "English",
        "Turkish"
    ),
    "source_url" => "https://github.com/tsugitools/llmrater",
    "placements" => array(
        "course_navigation", 
        "homework_submission"
    ),
    "screen_shots" => array(
        "store/screen-01.png",
        "store/screen-02.png",
        "store/screen-03.png"
    )
);