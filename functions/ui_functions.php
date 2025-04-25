<?php
namespace LLMRater\Functions;

class UI {
    /**
     * Renders the LTI iframe resize handler script
     */
    public static function renderFrameResizeScript() {
        ?>
        <script>
        function sendLTIFrameHeight() {
            try {
                const height = Math.max(
                    document.documentElement.clientHeight,
                    document.documentElement.scrollHeight,
                    document.documentElement.offsetHeight
                );
                
                if (window.parent && window.parent !== window) {
                    const message = {
                        subject: 'lti.frameResize',
                        height: height
                    };
                    window.parent.postMessage(JSON.stringify(message), '*');
                }
            } catch (e) {
                console.error('Error sending frame height:', e);
            }
        }

        window.addEventListener('load', sendLTIFrameHeight);
        window.addEventListener('resize', sendLTIFrameHeight);

        document.addEventListener('DOMContentLoaded', function() {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                modal.addEventListener('shown.bs.modal', sendLTIFrameHeight);
                modal.addEventListener('hidden.bs.modal', sendLTIFrameHeight);
            });
        });

        setInterval(sendLTIFrameHeight, 1000);
        </script>
        <?php
    }

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