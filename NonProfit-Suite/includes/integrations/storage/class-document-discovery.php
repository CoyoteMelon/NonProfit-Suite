<?php
/**
 * Document Discovery Engine
 *
 * AI-powered system to analyze, categorize, and organize existing documents.
 * Useful for nonprofits with accumulated documents in inconsistent naming/structure.
 *
 * @package NonprofitSuite
 * @subpackage Integrations\Storage
 * @since 1.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NonprofitSuite_Document_Discovery
 *
 * Automatically discovers, analyzes, and organizes documents using AI.
 *
 * Features:
 * - OCR for scanned documents (images, PDFs)
 * - AI content analysis and summarization
 * - Automatic categorization and tagging
 * - Entity extraction (people, organizations, dates)
 * - Suggested organization structure
 * - Batch processing with queue
 * - Confidence scoring for review
 *
 * @since 1.0.0
 */
class NonprofitSuite_Document_Discovery {

    /**
     * Singleton instance
     *
     * @var NonprofitSuite_Document_Discovery
     */
    private static $instance = null;

    /**
     * AI Adapter instance
     *
     * @var NonprofitSuite_AI_Adapter_Interface
     */
    private $ai_adapter;

    /**
     * Get singleton instance
     *
     * @return NonprofitSuite_Document_Discovery
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Get active AI provider
        $integration_manager = NonprofitSuite_Integration_Manager::get_instance();
        $this->ai_adapter = $integration_manager->get_active_provider('ai');

        // Hook into file upload to trigger automatic discovery
        add_action('ns_storage_file_uploaded', array($this, 'queue_discovery'), 10, 2);
    }

    /**
     * Queue a file for discovery analysis
     *
     * @param string $file_id   File UUID.
     * @param array  $file_data File metadata.
     * @return bool|WP_Error True on success, error on failure.
     */
    public function queue_discovery($file_id, $file_data = array()) {
        global $wpdb;

        // Check if already discovered
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ns_document_discovery WHERE file_id = %d",
            $file_data['id']
        ));

        if ($exists) {
            return new WP_Error('already_discovered', 'Document already in discovery queue or processed.');
        }

        // Insert into discovery table
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'ns_document_discovery',
            array(
                'file_id' => $file_data['id'],
                'discovery_status' => 'pending',
                'discovered_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s')
        );

        if (!$inserted) {
            return new WP_Error('queue_failed', 'Failed to queue document for discovery.');
        }

        // Trigger background processing
        do_action('ns_discovery_queued', $file_id, $file_data);

        return true;
    }

    /**
     * Process discovery for a file
     *
     * @param string $file_id File UUID.
     * @return array|WP_Error Discovery results or error.
     */
    public function process_discovery($file_id) {
        global $wpdb;

        // Get file info
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ns_storage_files WHERE file_uuid = %s",
            $file_id
        ));

        if (!$file) {
            return new WP_Error('file_not_found', 'File not found.');
        }

        // Update status to processing
        $wpdb->update(
            $wpdb->prefix . 'ns_document_discovery',
            array('discovery_status' => 'processing'),
            array('file_id' => $file->id),
            array('%s'),
            array('%d')
        );

        // Step 1: Extract text content
        $text_content = $this->extract_text($file);

        if (is_wp_error($text_content)) {
            $this->mark_discovery_failed($file->id, $text_content->get_error_message());
            return $text_content;
        }

        // Step 2: Analyze with AI if available
        $ai_analysis = array();

        if ($this->ai_adapter && !empty($text_content)) {
            $ai_analysis = $this->analyze_with_ai($text_content, $file);
        }

        // Step 3: Extract metadata
        $metadata = $this->extract_metadata($text_content, $file);

        // Step 4: Categorize document
        $category = $this->categorize_document($text_content, $ai_analysis, $file);

        // Step 5: Extract entities
        $entities = $this->extract_entities($text_content, $ai_analysis);

        // Step 6: Generate summary
        $summary = $this->generate_summary($text_content, $ai_analysis);

        // Step 7: Generate tags
        $tags = $this->generate_tags($text_content, $ai_analysis, $category);

        // Calculate confidence score
        $confidence = $this->calculate_confidence($ai_analysis, $metadata);

        // Save discovery results
        $saved = $this->save_discovery_results($file->id, array(
            'ocr_text' => $text_content,
            'discovered_category' => $category['category'],
            'discovered_subcategory' => $category['subcategory'],
            'auto_tags' => json_encode($tags),
            'content_summary' => $summary,
            'document_date' => $metadata['date'],
            'key_entities' => json_encode($entities),
            'language' => $metadata['language'],
            'confidence_score' => $confidence,
            'needs_review' => $confidence < 0.75,
            'discovery_status' => 'completed',
        ));

        if (is_wp_error($saved)) {
            return $saved;
        }

        do_action('ns_discovery_completed', $file_id, $saved);

        return $saved;
    }

    /**
     * Extract text from document
     *
     * Handles PDFs, images (OCR), and text files.
     *
     * @param object $file File database row.
     * @return string|WP_Error Extracted text or error.
     */
    private function extract_text($file) {
        // Get file from local storage or cache
        $orchestrator = NonprofitSuite_Storage_Orchestrator::get_instance();
        $local_path = $orchestrator->get_local_path($file->file_uuid);

        if (is_wp_error($local_path) || !file_exists($local_path)) {
            return new WP_Error('file_not_accessible', 'Cannot access file for text extraction.');
        }

        $mime_type = $file->mime_type;
        $text = '';

        // Text files - direct read
        if (strpos($mime_type, 'text/') === 0) {
            $text = file_get_contents($local_path);
        }

        // PDFs - use pdftotext if available, or AI
        elseif ($mime_type === 'application/pdf') {
            $text = $this->extract_pdf_text($local_path);
        }

        // Images - OCR
        elseif (strpos($mime_type, 'image/') === 0) {
            $text = $this->extract_image_text($local_path);
        }

        // Word documents - docx
        elseif ($mime_type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            $text = $this->extract_docx_text($local_path);
        }

        // Unknown type
        else {
            return new WP_Error('unsupported_type', 'Document type not supported for text extraction: ' . $mime_type);
        }

        return $text;
    }

    /**
     * Extract text from PDF
     *
     * @param string $file_path Path to PDF file.
     * @return string Extracted text.
     */
    private function extract_pdf_text($file_path) {
        // Try pdftotext command if available
        if (function_exists('shell_exec')) {
            $output = shell_exec('pdftotext ' . escapeshellarg($file_path) . ' -');
            if (!empty($output)) {
                return $output;
            }
        }

        // Fallback: Use AI for PDF content extraction
        if ($this->ai_adapter) {
            return 'PDF content extraction via AI (placeholder)';
        }

        return '';
    }

    /**
     * Extract text from image using OCR
     *
     * @param string $file_path Path to image file.
     * @return string Extracted text.
     */
    private function extract_image_text($file_path) {
        // Try tesseract OCR if available
        if (function_exists('shell_exec')) {
            $output = shell_exec('tesseract ' . escapeshellarg($file_path) . ' stdout 2>/dev/null');
            if (!empty($output)) {
                return $output;
            }
        }

        // Fallback: Use AI vision for OCR
        if ($this->ai_adapter && method_exists($this->ai_adapter, 'analyze_image')) {
            $result = $this->ai_adapter->analyze_image($file_path, array(
                'task' => 'ocr',
            ));

            if (!is_wp_error($result) && isset($result['text'])) {
                return $result['text'];
            }
        }

        return '';
    }

    /**
     * Extract text from DOCX file
     *
     * @param string $file_path Path to DOCX file.
     * @return string Extracted text.
     */
    private function extract_docx_text($file_path) {
        $text = '';

        // DOCX is a ZIP archive containing XML
        $zip = new ZipArchive();
        if ($zip->open($file_path) === true) {
            $xml = $zip->getFromName('word/document.xml');
            if ($xml) {
                $dom = new DOMDocument();
                $dom->loadXML($xml);
                $paragraphs = $dom->getElementsByTagName('p');
                foreach ($paragraphs as $p) {
                    $text .= $p->textContent . "\n";
                }
            }
            $zip->close();
        }

        return $text;
    }

    /**
     * Analyze content with AI
     *
     * @param string $text Text content.
     * @param object $file File database row.
     * @return array Analysis results.
     */
    private function analyze_with_ai($text, $file) {
        if (!$this->ai_adapter || empty($text)) {
            return array();
        }

        $prompt = "Analyze this nonprofit organization document and provide:
1. Category (legal, financial, meeting-minutes, policy, correspondence, grant, report, contract, other)
2. Subcategory (be specific)
3. Summary (2-3 sentences)
4. Key entities (people, organizations, places)
5. Important dates
6. Suggested tags (3-5 keywords)
7. Language

Document filename: {$file->filename}
Document content:
" . substr($text, 0, 4000); // Limit to avoid token limits

        $result = $this->ai_adapter->generate_text($prompt, array(
            'max_tokens' => 500,
            'temperature' => 0.3, // Low temperature for factual analysis
        ));

        if (is_wp_error($result)) {
            error_log('AI analysis failed: ' . $result->get_error_message());
            return array();
        }

        // Parse AI response (expecting structured format)
        return $this->parse_ai_response($result);
    }

    /**
     * Parse AI response into structured data
     *
     * @param string $response AI response text.
     * @return array Parsed data.
     */
    private function parse_ai_response($response) {
        // Simple parsing - in production, use JSON mode or structured prompts
        $data = array();

        if (preg_match('/Category:\s*(.+)/i', $response, $matches)) {
            $data['category'] = trim($matches[1]);
        }

        if (preg_match('/Subcategory:\s*(.+)/i', $response, $matches)) {
            $data['subcategory'] = trim($matches[1]);
        }

        if (preg_match('/Summary:\s*(.+)/is', $response, $matches)) {
            $data['summary'] = trim($matches[1]);
        }

        if (preg_match('/Tags:\s*(.+)/i', $response, $matches)) {
            $tags = explode(',', $matches[1]);
            $data['tags'] = array_map('trim', $tags);
        }

        return $data;
    }

    /**
     * Extract metadata from content
     *
     * @param string $text Text content.
     * @param object $file File database row.
     * @return array Metadata.
     */
    private function extract_metadata($text, $file) {
        $metadata = array(
            'date' => null,
            'language' => 'en',
        );

        // Extract dates using regex
        if (preg_match('/\b(\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}|\d{4}[\/-]\d{1,2}[\/-]\d{1,2})\b/', $text, $matches)) {
            $metadata['date'] = date('Y-m-d', strtotime($matches[1]));
        }

        // Detect language (simple heuristic)
        $metadata['language'] = $this->detect_language($text);

        return $metadata;
    }

    /**
     * Detect text language
     *
     * @param string $text Text content.
     * @return string Language code (ISO 639-1).
     */
    private function detect_language($text) {
        // Very basic detection - in production, use a proper library
        $sample = substr($text, 0, 500);

        // Count common English words
        $english_words = array('the', 'and', 'is', 'to', 'in', 'of', 'for', 'on', 'with');
        $count = 0;
        foreach ($english_words as $word) {
            $count += substr_count(strtolower($sample), ' ' . $word . ' ');
        }

        return $count > 5 ? 'en' : 'unknown';
    }

    /**
     * Categorize document
     *
     * @param string $text         Text content.
     * @param array  $ai_analysis  AI analysis results.
     * @param object $file         File database row.
     * @return array Category and subcategory.
     */
    private function categorize_document($text, $ai_analysis, $file) {
        // Use AI category if available
        if (!empty($ai_analysis['category'])) {
            return array(
                'category' => $ai_analysis['category'],
                'subcategory' => isset($ai_analysis['subcategory']) ? $ai_analysis['subcategory'] : null,
            );
        }

        // Fallback: keyword-based categorization
        $filename_lower = strtolower($file->filename);
        $text_lower = strtolower(substr($text, 0, 1000));

        $categories = array(
            'legal' => array('articles of incorporation', 'bylaws', 'contract', 'agreement', 'legal'),
            'financial' => array('budget', 'financial', 'invoice', 'receipt', 'tax', '990', 'audit'),
            'meeting-minutes' => array('minutes', 'meeting', 'board meeting', 'committee meeting'),
            'grant' => array('grant', 'proposal', 'funding', 'application'),
            'policy' => array('policy', 'procedure', 'guidelines', 'handbook'),
            'report' => array('report', 'annual report', 'quarterly'),
        );

        foreach ($categories as $cat => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($filename_lower, $keyword) !== false || strpos($text_lower, $keyword) !== false) {
                    return array(
                        'category' => $cat,
                        'subcategory' => null,
                    );
                }
            }
        }

        return array(
            'category' => 'general',
            'subcategory' => null,
        );
    }

    /**
     * Extract entities from text
     *
     * @param string $text        Text content.
     * @param array  $ai_analysis AI analysis results.
     * @return array Entities.
     */
    private function extract_entities($text, $ai_analysis) {
        $entities = array(
            'people' => array(),
            'organizations' => array(),
            'places' => array(),
        );

        // Use AI entities if available
        if (!empty($ai_analysis['entities'])) {
            return $ai_analysis['entities'];
        }

        // Simple named entity extraction (capitalized words)
        preg_match_all('/\b[A-Z][a-z]+ [A-Z][a-z]+\b/', $text, $matches);
        $entities['people'] = array_unique(array_slice($matches[0], 0, 10));

        return $entities;
    }

    /**
     * Generate content summary
     *
     * @param string $text        Text content.
     * @param array  $ai_analysis AI analysis results.
     * @return string Summary.
     */
    private function generate_summary($text, $ai_analysis) {
        if (!empty($ai_analysis['summary'])) {
            return $ai_analysis['summary'];
        }

        // Fallback: first 200 characters
        return substr($text, 0, 200) . '...';
    }

    /**
     * Generate tags
     *
     * @param string $text        Text content.
     * @param array  $ai_analysis AI analysis results.
     * @param array  $category    Category info.
     * @return array Tags.
     */
    private function generate_tags($text, $ai_analysis, $category) {
        $tags = array();

        // Add AI tags
        if (!empty($ai_analysis['tags'])) {
            $tags = array_merge($tags, $ai_analysis['tags']);
        }

        // Add category as tag
        if (!empty($category['category'])) {
            $tags[] = $category['category'];
        }

        return array_unique($tags);
    }

    /**
     * Calculate confidence score
     *
     * @param array $ai_analysis AI analysis results.
     * @param array $metadata    Extracted metadata.
     * @return float Confidence score (0.0 to 1.0).
     */
    private function calculate_confidence($ai_analysis, $metadata) {
        $score = 0.5; // Base score

        // Boost if AI was used
        if (!empty($ai_analysis)) {
            $score += 0.3;
        }

        // Boost if date was extracted
        if (!empty($metadata['date'])) {
            $score += 0.1;
        }

        // Boost if category was identified
        if (!empty($ai_analysis['category'])) {
            $score += 0.1;
        }

        return min(1.0, $score);
    }

    /**
     * Save discovery results to database
     *
     * @param int   $file_id Internal file ID.
     * @param array $results Discovery results.
     * @return bool|WP_Error True on success, error on failure.
     */
    private function save_discovery_results($file_id, $results) {
        global $wpdb;

        $updated = $wpdb->update(
            $wpdb->prefix . 'ns_document_discovery',
            $results,
            array('file_id' => $file_id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s'),
            array('%d')
        );

        if ($updated === false) {
            return new WP_Error('save_failed', 'Failed to save discovery results.');
        }

        return true;
    }

    /**
     * Mark discovery as failed
     *
     * @param int    $file_id Internal file ID.
     * @param string $error   Error message.
     * @return void
     */
    private function mark_discovery_failed($file_id, $error) {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'ns_document_discovery',
            array(
                'discovery_status' => 'failed',
                'content_summary' => 'Discovery failed: ' . $error,
            ),
            array('file_id' => $file_id),
            array('%s', '%s'),
            array('%d')
        );
    }

    /**
     * Batch process pending discoveries
     *
     * @param int $limit Number of files to process.
     * @return int Number processed.
     */
    public function process_batch($limit = 10) {
        global $wpdb;

        $pending = $wpdb->get_results($wpdb->prepare(
            "SELECT f.file_uuid
            FROM {$wpdb->prefix}ns_document_discovery d
            INNER JOIN {$wpdb->prefix}ns_storage_files f ON d.file_id = f.id
            WHERE d.discovery_status = 'pending'
            ORDER BY d.discovered_at ASC
            LIMIT %d",
            $limit
        ));

        $processed = 0;

        foreach ($pending as $item) {
            $result = $this->process_discovery($item->file_uuid);
            if (!is_wp_error($result)) {
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Accept discovery suggestions
     *
     * Applies the discovered category, tags, etc. to the actual file record.
     *
     * @param string $file_id File UUID.
     * @return bool|WP_Error True on success, error on failure.
     */
    public function accept_discovery($file_id) {
        global $wpdb;

        // Get discovery results
        $discovery = $wpdb->get_row($wpdb->prepare(
            "SELECT d.* FROM {$wpdb->prefix}ns_document_discovery d
            INNER JOIN {$wpdb->prefix}ns_storage_files f ON d.file_id = f.id
            WHERE f.file_uuid = %s",
            $file_id
        ));

        if (!$discovery) {
            return new WP_Error('discovery_not_found', 'Discovery results not found.');
        }

        // Update file record with discovered data
        $updated = $wpdb->update(
            $wpdb->prefix . 'ns_storage_files',
            array(
                'category' => $discovery->discovered_category,
                // Could also update filename, folder_path, etc.
            ),
            array('file_uuid' => $file_id),
            array('%s'),
            array('%s')
        );

        if ($updated === false) {
            return new WP_Error('update_failed', 'Failed to apply discovery results.');
        }

        // Mark as reviewed
        $wpdb->update(
            $wpdb->prefix . 'ns_document_discovery',
            array(
                'needs_review' => 0,
                'reviewed_by' => get_current_user_id(),
                'reviewed_at' => current_time('mysql'),
            ),
            array('file_id' => $discovery->file_id),
            array('%d', '%d', '%s'),
            array('%d')
        );

        return true;
    }
}
