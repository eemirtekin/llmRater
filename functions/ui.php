<?php
namespace LLMRater\Functions;

class UI {
    /**
     * Renders the menu for the application
     */
    public static function renderMenu($LAUNCH, $menu) {
        $menu->addLeft('Home', 'index.php');

        if ($LAUNCH->user->instructor) {
            $menu->addRight('Create Question', '#', false, 'data-toggle="modal" data-target="#createQuestionModal"');
            $menu->addRight('Settings', '#', false, \Tsugi\UI\SettingsForm::attr());
        }

        return $menu;
    }

    /**
     * Clean and format markdown text
     */
    public static function formatMarkdown($text, $parsedown) {
        $cleanText = strip_tags($text);
        $cleanText = implode("\n", array_map('trim', explode("\n", $cleanText)));
        return $parsedown->text($cleanText);
    }
}