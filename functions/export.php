<?php
namespace LLMRater\Functions;

class Export {
    /**
     * Exports responses to CSV
     */
    public static function exportToCSV($responses, $question, $output) {
        // Add UTF-8 BOM for Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Write CSV header
        fputcsv($output, [
            'Student',
            'Email',
            'Question',
            'Answer',
            'Submitted At',
            'Evaluation',
            'Evaluated At'
        ]);

        // Write data rows with chunking for memory efficiency
        $chunkSize = 100;
        $processed = 0;
        $total = count($responses);

        while ($processed < $total) {
            $chunk = array_slice($responses, $processed, $chunkSize);
            
            foreach ($chunk as $response) {
                fputcsv($output, [
                    $response['displayname'] ?? 'Unknown',
                    $response['email'] ?? '',
                    strip_tags($response['question']),
                    $response['answer'],
                    $response['submitted_at'],
                    $response['evaluation_text'] ?? '',
                    $response['evaluated_at'] ?? ''
                ]);
            }

            $processed += count($chunk);
            ob_flush();
            flush();
        }
    }

    /**
     * Generates a safe filename for export
     */
    public static function generateExportFilename($question) {
        $safeTitle = preg_replace('/[^a-z0-9]+/i', '_', $question['title']);
        return sprintf(
            'responses_%s_%s_%s.csv',
            $safeTitle,
            $question['question_id'],
            date('Y-m-d_His')
        );
    }

    /**
     * Sets appropriate headers for CSV download
     */
    public static function setExportHeaders($filename) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
    }
}