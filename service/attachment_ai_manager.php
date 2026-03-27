<?php
/**
 * Admin Helper - Attachment AI detection and storage.
 *
 * @package bastien59960/adminhelper
 * @license GPL-2.0-only
 */

namespace bastien59960\adminhelper\service;

class attachment_ai_manager
{
    const SCAN_STATUS_DETECTED = 'detected';
    const SCAN_STATUS_CLEAN = 'clean';
    const SCAN_STATUS_FILE_NOT_FOUND = 'file_not_found';
    const SCAN_STATUS_SCAN_ERROR = 'scan_error';

    protected $db;
    protected $config;
    protected $root_path;
    protected $table_prefix;
    protected $flags_table;

    public function __construct(
        \phpbb\db\driver\driver_interface $db,
        \phpbb\config\config $config,
        $root_path,
        $table_prefix
    ) {
        $this->db = $db;
        $this->config = $config;
        $this->root_path = $root_path;
        $this->table_prefix = $table_prefix;
        $this->flags_table = $table_prefix . 'adminhelper_attachment_ai';
    }

    public function get_flags_table()
    {
        return $this->flags_table;
    }

    public function parse_attachment_id_csv($csv)
    {
        $out = [];
        foreach (explode(',', (string) $csv) as $raw) {
            $id = (int) trim((string) $raw);
            if ($id > 0) {
                $out[$id] = $id;
            }
        }
        return array_values($out);
    }

    public function normalize_provider_key($provider)
    {
        $provider = strtolower(trim((string) $provider));
        $allowed = [
            'gemini',
            'chatgpt',
            'grok',
            'dall_e',
            'midjourney',
            'stable_diffusion',
            'comfyui',
            'adobe_firefly',
            'imagen',
            'black_forest_labs',
            'flux',
            'leonardo',
            'ideogram',
            'invokeai',
            'automatic1111',
            'novelai',
            'playgroundai',
            'openai',
        ];

        return in_array($provider, $allowed, true) ? $provider : '';
    }

    public function is_image_attachment(array $attachment)
    {
        $mimetype = strtolower((string) ($attachment['mimetype'] ?? ''));
        if ($mimetype !== '' && strpos($mimetype, 'image/') === 0) {
            return true;
        }

        $extension = strtolower((string) ($attachment['extension'] ?? ''));
        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif', 'heic', 'heif', 'tif', 'tiff'], true);
    }

    public function get_post_image_attachment_ids($post_id)
    {
        $ids = [];
        foreach ($this->get_post_image_attachments((int) $post_id) as $attachment) {
            $attach_id = (int) ($attachment['attach_id'] ?? 0);
            if ($attach_id > 0) {
                $ids[] = $attach_id;
            }
        }
        return $ids;
    }

    public function get_posting_state(array $attachment_ids, array $requested_checked_ids = [], array $requested_forced_ids = [])
    {
        $attachment_ids = array_values(array_unique(array_map('intval', $attachment_ids)));
        $attachment_ids = array_values(array_filter($attachment_ids, function ($id) {
            return $id > 0;
        }));

        $state_map = $this->get_flag_map_by_attachment_ids($attachment_ids, true);
        $checked_ids = [];
        $forced_ids = [];

        if (!empty($requested_checked_ids)) {
            foreach ($requested_checked_ids as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $checked_ids[$id] = $id;
                }
            }
        } else {
            foreach ($state_map as $attach_id => $row) {
                if (!empty($row['is_ai_generated'])) {
                    $checked_ids[(int) $attach_id] = (int) $attach_id;
                }
            }
        }

        if (!empty($requested_forced_ids)) {
            foreach ($requested_forced_ids as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $forced_ids[$id] = $id;
                }
            }
        }

        $provider_map = [];
        foreach ($state_map as $attach_id => $row) {
            $provider = $this->normalize_provider_key($row['ai_provider'] ?? '');
            if ($provider !== '') {
                $provider_map[(int) $attach_id] = $provider;
            }
        }

        foreach ($state_map as $attach_id => $row) {
            $attach_id = (int) $attach_id;
            if (!empty($row['is_forced'])) {
                $forced_ids[$attach_id] = $attach_id;
                $checked_ids[$attach_id] = $attach_id;
                $provider = $this->normalize_provider_key($row['ai_provider'] ?? '');
                if ($provider !== '') {
                    $provider_map[$attach_id] = $provider;
                }
            }
        }

        $states = [];
        foreach ($state_map as $attach_id => $row) {
            $states[(int) $attach_id] = [
                'generated' => !empty($row['is_ai_generated']) ? 1 : 0,
                'forced' => !empty($row['is_forced']) ? 1 : 0,
                'provider' => (string) ($row['ai_provider'] ?? ''),
                'source' => (string) ($row['detection_source'] ?? ''),
                'reason' => (string) ($row['detection_reason'] ?? ''),
                'scan_status' => (string) ($row['scan_status'] ?? ''),
            ];
        }

        ksort($checked_ids);
        ksort($forced_ids);
        ksort($states);

        return [
            'checked_ids' => array_values($checked_ids),
            'forced_ids' => array_values($forced_ids),
            'provider_map' => $provider_map,
            'states' => $states,
        ];
    }

    public function get_attachment_state_by_id($attach_id, $auto_scan_missing = true)
    {
        $attach_id = (int) $attach_id;
        if ($attach_id <= 0) {
            return [];
        }

        $row = $this->get_flag_row_by_attachment_id($attach_id);
        if (!empty($row)) {
            return $row;
        }

        if (!$auto_scan_missing) {
            return [];
        }

        return $this->record_attachment_scan_by_id($attach_id);
    }

    public function get_flag_map_by_attachment_ids(array $attachment_ids, $auto_scan_missing = false)
    {
        $attachment_ids = array_values(array_unique(array_map('intval', $attachment_ids)));
        $attachment_ids = array_values(array_filter($attachment_ids, function ($id) {
            return $id > 0;
        }));

        if (empty($attachment_ids)) {
            return [];
        }

        $sql = 'SELECT *
                FROM ' . $this->flags_table . '
                WHERE ' . $this->db->sql_in_set('attach_id', $attachment_ids);
        $result = $this->db->sql_query($sql);
        $map = [];
        while ($row = $this->db->sql_fetchrow($result)) {
            $map[(int) $row['attach_id']] = $row;
        }
        $this->db->sql_freeresult($result);

        if ($auto_scan_missing) {
            foreach ($attachment_ids as $attach_id) {
                if (!isset($map[$attach_id])) {
                    $row = $this->record_attachment_scan_by_id($attach_id);
                    if (!empty($row)) {
                        $map[$attach_id] = $row;
                    }
                }
            }
        }

        return $map;
    }

    public function record_attachment_scan_by_id($attach_id)
    {
        $attachment = $this->load_attachment_row_by_id((int) $attach_id);
        if (empty($attachment) || !$this->is_image_attachment($attachment)) {
            return [];
        }

        return $this->record_attachment_scan($attachment);
    }

    public function sync_post_flags($post_id, $user_id, array $checked_ids)
    {
        $post_id = (int) $post_id;
        $user_id = (int) $user_id;
        if ($post_id <= 0) {
            return;
        }

        $checked_map = [];
        foreach ($checked_ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $checked_map[$id] = true;
            }
        }

        $attachments = $this->get_post_image_attachments($post_id);
        $now = time();

        foreach ($attachments as $attachment) {
            $attach_id = (int) ($attachment['attach_id'] ?? 0);
            if ($attach_id <= 0) {
                continue;
            }

            $state = $this->get_attachment_state_by_id($attach_id, true);
            $is_forced = !empty($state['is_forced']);
            $is_ai_generated = $is_forced || !empty($checked_map[$attach_id]);
            $scan_status = (string) ($state['scan_status'] ?? '');
            $detection_source = (string) ($state['detection_source'] ?? '');
            $detection_reason = (string) ($state['detection_reason'] ?? '');
            $created_at = (int) ($state['created_at'] ?? $now);
            $ai_provider = $is_ai_generated
                ? $this->normalize_provider_key($state['ai_provider'] ?? '')
                : '';

            $row = [
                'attach_id' => $attach_id,
                'post_id' => $post_id,
                'user_id' => $user_id > 0 ? $user_id : (int) ($attachment['user_id'] ?? 0),
                'is_ai_generated' => $is_ai_generated ? 1 : 0,
                'is_forced' => $is_forced ? 1 : 0,
                'ai_provider' => $ai_provider,
                'scan_status' => $scan_status,
                'detection_source' => $detection_source,
                'detection_reason' => $detection_reason,
                'created_at' => $created_at > 0 ? $created_at : $now,
                'updated_at' => $now,
            ];

            $this->upsert_flag_row($row);
        }
    }

    public function scan_attachment_batch($limit = 500, $time_budget_seconds = 20.0)
    {
        $limit = max(1, min(1000, (int) $limit));
        $time_budget_seconds = max(1.0, (float) $time_budget_seconds);
        $started_at = microtime(true);

        $total_candidates = $this->count_scan_candidates();
        $processed = 0;
        $detected = 0;
        $clean = 0;
        $errors = 0;
        $timed_out = false;
        $has_more = true;

        while ($has_more) {
            if ((microtime(true) - $started_at) >= $time_budget_seconds) {
                $timed_out = true;
                break;
            }

            $batch_rows = $this->load_scan_batch($limit);
            if (empty($batch_rows)) {
                $has_more = false;
                break;
            }

            foreach ($batch_rows as $attachment) {
                if ((microtime(true) - $started_at) >= $time_budget_seconds) {
                    $timed_out = true;
                    break 2;
                }

                $processed++;
                $row = $this->record_attachment_scan($attachment);
                $scan_status = (string) ($row['scan_status'] ?? '');

                if ($scan_status === self::SCAN_STATUS_DETECTED) {
                    $detected++;
                } else if ($scan_status === self::SCAN_STATUS_CLEAN) {
                    $clean++;
                } else {
                    $errors++;
                }
            }

            if (count($batch_rows) < $limit) {
                $has_more = false;
            }
        }

        $processed_total = $this->count_scan_processed();
        $remaining = max(0, $total_candidates - $processed_total);

        return [
            'processed' => $processed,
            'detected' => $detected,
            'clean' => $clean,
            'errors' => $errors,
            'timed_out' => $timed_out,
            'total_candidates' => $total_candidates,
            'processed_total' => $processed_total,
            'remaining' => $remaining,
        ];
    }

    public function get_scan_stats()
    {
        $candidates = $this->count_scan_candidates();
        $processed = $this->count_scan_processed();
        $remaining = max(0, $candidates - $processed);
        $detected = $this->count_detected_total();
        $manual = $this->count_manual_total();

        return [
            'attachments_candidates' => $candidates,
            'attachments_processed' => min($candidates, $processed),
            'attachments_remaining' => $remaining,
            'attachments_detected' => $detected,
            'attachments_manual' => $manual,
            'attachments_progress_pct' => ($candidates > 0)
                ? (int) floor(($processed * 100) / $candidates)
                : 100,
        ];
    }

    public function get_recent_flagged_rows($limit = 50)
    {
        $limit = max(1, min(200, (int) $limit));
        $sql = 'SELECT ai.attach_id, ai.post_id, ai.is_forced, ai.scan_status, ai.detection_source,
                       ai.detection_reason, ai.ai_provider, ai.updated_at,
                       a.real_filename
                FROM ' . $this->flags_table . ' ai
                LEFT JOIN ' . ATTACHMENTS_TABLE . ' a
                    ON a.attach_id = ai.attach_id
                WHERE ai.is_ai_generated = 1
                ORDER BY ai.updated_at DESC, ai.attach_id DESC';
        $result = $this->db->sql_query_limit($sql, $limit);
        $rows = [];
        while ($row = $this->db->sql_fetchrow($result)) {
            $rows[] = $row;
        }
        $this->db->sql_freeresult($result);
        return $rows;
    }

    public function detect_attachment(array $attachment)
    {
        $none = [
            'is_ai_generated' => 0,
            'is_forced' => 0,
            'ai_provider' => '',
            'scan_status' => self::SCAN_STATUS_SCAN_ERROR,
            'detection_source' => '',
            'detection_reason' => '',
        ];

        $file_path = $this->resolve_attachment_path($attachment);
        if ($file_path === '' || !is_file($file_path)) {
            $none['scan_status'] = self::SCAN_STATUS_FILE_NOT_FOUND;
            $none['detection_reason'] = 'file_not_found';
            return $none;
        }

        $raw_sample = $this->read_binary_sample($file_path);
        $metadata_text = $this->collect_metadata_text($file_path);
        $combined = strtolower($metadata_text . "\n" . $raw_sample);
        $provider_haystack = $combined . "\n" . strtolower((string) ($attachment['real_filename'] ?? ''));
        $provider = $this->detect_ai_provider($provider_haystack);

        if ($this->contains_ai_c2pa_claim($combined)) {
            return [
                'is_ai_generated' => 1,
                'is_forced' => 1,
                'ai_provider' => $provider,
                'scan_status' => self::SCAN_STATUS_DETECTED,
                'detection_source' => 'c2pa',
                'detection_reason' => $this->resolve_c2pa_reason($combined),
            ];
        }

        if ($this->contains_known_ai_tool_signature($combined)) {
            return [
                'is_ai_generated' => 1,
                'is_forced' => 1,
                'ai_provider' => $provider,
                'scan_status' => self::SCAN_STATUS_DETECTED,
                'detection_source' => 'metadata',
                'detection_reason' => 'metadata_ai_tool',
            ];
        }

        if ($this->contains_prompt_signature($combined)) {
            return [
                'is_ai_generated' => 1,
                'is_forced' => 1,
                'ai_provider' => $provider,
                'scan_status' => self::SCAN_STATUS_DETECTED,
                'detection_source' => 'prompt',
                'detection_reason' => 'metadata_prompt_signature',
            ];
        }

        return [
            'is_ai_generated' => 0,
            'is_forced' => 0,
            'ai_provider' => '',
            'scan_status' => self::SCAN_STATUS_CLEAN,
            'detection_source' => '',
            'detection_reason' => '',
        ];
    }

    private function contains_ai_c2pa_claim($haystack)
    {
        if ($haystack === '') {
            return false;
        }

        if (strpos($haystack, 'trainedalgorithmicmedia') !== false) {
            return true;
        }

        if (strpos($haystack, 'c2pa.ai_generated_content') !== false) {
            return true;
        }

        if (strpos($haystack, 'created by google generative ai') !== false) {
            return true;
        }

        return false;
    }

    private function resolve_c2pa_reason($haystack)
    {
        if (strpos($haystack, 'c2pa.ai_generated_content') !== false) {
            return 'c2pa_ai_generated_content';
        }

        if (strpos($haystack, 'created by google generative ai') !== false) {
            return 'c2pa_google_generative_ai';
        }

        return 'c2pa_trained_algorithmic_media';
    }

    private function contains_known_ai_tool_signature($haystack)
    {
        if ($haystack === '') {
            return false;
        }

        $patterns = [
            'stable diffusion',
            'comfyui',
            'midjourney',
            'dall-e',
            'openai',
            'chatgpt',
            'gpt-4o',
            'gpt-image-1',
            'adobe firefly',
            'gemini',
            'imagen',
            'google generative ai',
            'black forest labs',
            'leonardo',
            'ideogram',
            'invokeai',
            'automatic1111',
            'novelai',
            'playgroundai',
            'flux.1',
            'flux.2',
        ];

        foreach ($patterns as $pattern) {
            if (strpos($haystack, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    private function detect_ai_provider($haystack)
    {
        if ($haystack === '') {
            return '';
        }

        $providers = [
            'chatgpt' => ['chatgpt', 'gpt-4o', 'gpt-image-1'],
            'gemini' => ['gemini', 'google generative ai'],
            'grok' => ['grok', 'x.ai', 'xai'],
            'dall_e' => ['dall-e'],
            'midjourney' => ['midjourney'],
            'stable_diffusion' => ['stable diffusion'],
            'comfyui' => ['comfyui'],
            'adobe_firefly' => ['adobe firefly', 'firefly'],
            'imagen' => ['imagen'],
            'black_forest_labs' => ['black forest labs'],
            'flux' => ['flux.1', 'flux.2'],
            'leonardo' => ['leonardo'],
            'ideogram' => ['ideogram'],
            'invokeai' => ['invokeai'],
            'automatic1111' => ['automatic1111'],
            'novelai' => ['novelai'],
            'playgroundai' => ['playgroundai'],
            'openai' => ['openai'],
        ];

        foreach ($providers as $provider => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($haystack, $pattern) !== false) {
                    return $provider;
                }
            }
        }

        return '';
    }

    private function contains_prompt_signature($haystack)
    {
        if ($haystack === '') {
            return false;
        }

        $has_prompt_container = (
            strpos($haystack, 'parameters') !== false ||
            strpos($haystack, 'prompt') !== false ||
            strpos($haystack, 'negative prompt') !== false
        );

        if (!$has_prompt_container) {
            return false;
        }

        if (preg_match('/\bsteps:\s*\d+/i', $haystack)) {
            return true;
        }

        if (preg_match('/\bcfg\s*scale\b/i', $haystack)) {
            return true;
        }

        if (preg_match('/\bsampler\b/i', $haystack)) {
            return true;
        }

        if (preg_match('/\bseed:\s*\d+/i', $haystack)) {
            return true;
        }

        if (preg_match('/\bai generated image\b/i', $haystack)) {
            return true;
        }

        return false;
    }

    private function record_attachment_scan(array $attachment)
    {
        $attach_id = (int) ($attachment['attach_id'] ?? 0);
        if ($attach_id <= 0 || !$this->is_image_attachment($attachment)) {
            return [];
        }

        $existing = $this->get_flag_row_by_attachment_id($attach_id);
        $detection = $this->detect_attachment($attachment);
        $row = $this->merge_detected_state($attachment, $detection, $existing);
        $this->upsert_flag_row($row);
        return $row;
    }

    private function merge_detected_state(array $attachment, array $detection, array $existing)
    {
        $now = time();
        $attach_id = (int) ($attachment['attach_id'] ?? 0);
        $existing_is_forced = !empty($existing['is_forced']);
        $existing_is_generated = !empty($existing['is_ai_generated']);
        $detected_is_generated = !empty($detection['is_ai_generated']);

        $row = [
            'attach_id' => $attach_id,
            'post_id' => (int) ($attachment['post_id'] ?? ($existing['post_id'] ?? 0)),
            'user_id' => (int) ($attachment['user_id'] ?? ($existing['user_id'] ?? 0)),
            'ai_provider' => (string) ($detection['ai_provider'] ?? ''),
            'scan_status' => (string) ($detection['scan_status'] ?? ($existing['scan_status'] ?? '')),
            'detection_source' => (string) ($detection['detection_source'] ?? ''),
            'detection_reason' => (string) ($detection['detection_reason'] ?? ''),
            'created_at' => (int) ($existing['created_at'] ?? $now),
            'updated_at' => $now,
        ];

        if ($detected_is_generated) {
            $row['is_ai_generated'] = 1;
            $row['is_forced'] = 1;
        } else {
            $row['is_ai_generated'] = $existing_is_generated ? 1 : 0;
            $row['is_forced'] = $existing_is_forced ? 1 : 0;
            if ($row['is_forced']) {
                $row['is_ai_generated'] = 1;
            }

            if ($row['detection_source'] === '' && !empty($existing['detection_source'])) {
                $row['detection_source'] = (string) $existing['detection_source'];
            }
            if ($row['detection_reason'] === '' && !empty($existing['detection_reason'])) {
                $row['detection_reason'] = (string) $existing['detection_reason'];
            }
            if ($row['ai_provider'] === '' && !empty($existing['ai_provider'])) {
                $row['ai_provider'] = (string) $existing['ai_provider'];
            }
        }

        return $row;
    }

    private function upsert_flag_row(array $row)
    {
        if (empty($row['attach_id'])) {
            return;
        }

        $insert_sql = 'INSERT INTO ' . $this->flags_table . ' ' . $this->db->sql_build_array('INSERT', $row);
        $update_parts = [];
        foreach ($row as $column => $value) {
            if ($column === 'attach_id') {
                continue;
            }
            $update_parts[] = $column . ' = ' . $this->to_sql_value($value);
        }

        $sql = $insert_sql . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $update_parts);
        $this->db->sql_query($sql);
    }

    private function to_sql_value($value)
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return "'" . $this->db->sql_escape((string) $value) . "'";
    }

    private function load_attachment_row_by_id($attach_id)
    {
        $attach_id = (int) $attach_id;
        if ($attach_id <= 0) {
            return [];
        }

        $sql = 'SELECT attach_id, post_msg_id AS post_id, poster_id AS user_id,
                       real_filename, physical_filename, mimetype, extension, is_orphan, in_message
                FROM ' . ATTACHMENTS_TABLE . '
                WHERE attach_id = ' . $attach_id;
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        return is_array($row) ? $row : [];
    }

    private function get_post_image_attachments($post_id)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return [];
        }

        $sql = 'SELECT attach_id, post_msg_id AS post_id, poster_id AS user_id,
                       real_filename, physical_filename, mimetype, extension, is_orphan, in_message
                FROM ' . ATTACHMENTS_TABLE . '
                WHERE post_msg_id = ' . $post_id . '
                  AND is_orphan = 0
                  AND in_message = 0
                ORDER BY attach_id ASC';
        $result = $this->db->sql_query($sql);
        $rows = [];
        while ($row = $this->db->sql_fetchrow($result)) {
            if ($this->is_image_attachment($row)) {
                $rows[] = $row;
            }
        }
        $this->db->sql_freeresult($result);

        return $rows;
    }

    private function get_flag_row_by_attachment_id($attach_id)
    {
        $attach_id = (int) $attach_id;
        if ($attach_id <= 0) {
            return [];
        }

        $sql = 'SELECT *
                FROM ' . $this->flags_table . '
                WHERE attach_id = ' . $attach_id;
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        return is_array($row) ? $row : [];
    }

    private function resolve_attachment_path(array $attachment)
    {
        $upload_path = trim((string) ($this->config['upload_path'] ?? 'files'), '/');
        $physical = (string) ($attachment['physical_filename'] ?? '');
        if ($physical === '') {
            return '';
        }
        return $this->root_path . $upload_path . '/' . $physical;
    }

    private function read_binary_sample($file_path)
    {
        $size = @filesize($file_path);
        if (!$size || $size <= 0) {
            return '';
        }

        $bytes = ($size <= 8388608) ? $size : 2097152;
        $raw = @file_get_contents($file_path, false, null, 0, $bytes);
        if (!is_string($raw)) {
            return '';
        }

        return $raw;
    }

    private function collect_metadata_text($file_path)
    {
        if (!function_exists('exif_read_data')) {
            return '';
        }

        $exif = @exif_read_data($file_path, null, true);
        if (!is_array($exif)) {
            return '';
        }

        $chunks = [];
        foreach ($exif as $section => $values) {
            if (!is_array($values)) {
                continue;
            }

            foreach ($values as $key => $value) {
                if (is_array($value)) {
                    continue;
                }

                $key = (string) $key;
                if (
                    !preg_match('/(Software|UserComment|ImageDescription|XPComment|XPKeywords|Artist|HostComputer|Comment|Copyright|Parameters|Prompt)/i', $key)
                    && $section !== 'COMMENT'
                ) {
                    continue;
                }

                $text = trim((string) $value);
                if ($text === '') {
                    continue;
                }

                $chunks[] = $text;
            }
        }

        return implode("\n", $chunks);
    }

    private function count_scan_candidates()
    {
        $sql = 'SELECT COUNT(*) AS total
                FROM ' . ATTACHMENTS_TABLE . ' a
                WHERE a.is_orphan = 0
                  AND a.in_message = 0
                  AND (
                    a.mimetype LIKE \'image/%\'
                    OR a.extension IN (\'jpg\', \'jpeg\', \'png\', \'gif\', \'webp\', \'bmp\', \'avif\', \'heic\', \'heif\', \'tif\', \'tiff\')
                  )';
        $result = $this->db->sql_query($sql);
        $total = (int) $this->db->sql_fetchfield('total');
        $this->db->sql_freeresult($result);
        return $total;
    }

    private function count_scan_processed()
    {
        $sql = 'SELECT COUNT(*) AS total
                FROM ' . ATTACHMENTS_TABLE . ' a
                INNER JOIN ' . $this->flags_table . ' ai
                    ON ai.attach_id = a.attach_id
                WHERE a.is_orphan = 0
                  AND a.in_message = 0
                  AND (
                    a.mimetype LIKE \'image/%\'
                    OR a.extension IN (\'jpg\', \'jpeg\', \'png\', \'gif\', \'webp\', \'bmp\', \'avif\', \'heic\', \'heif\', \'tif\', \'tiff\')
                  )
                  AND ai.scan_status <> \'\'';
        $result = $this->db->sql_query($sql);
        $total = (int) $this->db->sql_fetchfield('total');
        $this->db->sql_freeresult($result);
        return $total;
    }

    private function count_detected_total()
    {
        $sql = 'SELECT COUNT(*) AS total
                FROM ' . ATTACHMENTS_TABLE . ' a
                INNER JOIN ' . $this->flags_table . ' ai
                    ON ai.attach_id = a.attach_id
                WHERE a.is_orphan = 0
                  AND a.in_message = 0
                  AND ai.is_ai_generated = 1
                  AND ai.is_forced = 1
                  AND (
                    a.mimetype LIKE \'image/%\'
                    OR a.extension IN (\'jpg\', \'jpeg\', \'png\', \'gif\', \'webp\', \'bmp\', \'avif\', \'heic\', \'heif\', \'tif\', \'tiff\')
                  )';
        $result = $this->db->sql_query($sql);
        $total = (int) $this->db->sql_fetchfield('total');
        $this->db->sql_freeresult($result);
        return $total;
    }

    private function count_manual_total()
    {
        $sql = 'SELECT COUNT(*) AS total
                FROM ' . ATTACHMENTS_TABLE . ' a
                INNER JOIN ' . $this->flags_table . ' ai
                    ON ai.attach_id = a.attach_id
                WHERE a.is_orphan = 0
                  AND a.in_message = 0
                  AND ai.is_ai_generated = 1
                  AND ai.is_forced = 0
                  AND (
                    a.mimetype LIKE \'image/%\'
                    OR a.extension IN (\'jpg\', \'jpeg\', \'png\', \'gif\', \'webp\', \'bmp\', \'avif\', \'heic\', \'heif\', \'tif\', \'tiff\')
                  )';
        $result = $this->db->sql_query($sql);
        $total = (int) $this->db->sql_fetchfield('total');
        $this->db->sql_freeresult($result);
        return $total;
    }

    private function load_scan_batch($limit)
    {
        $sql = 'SELECT a.attach_id, a.post_msg_id AS post_id, a.poster_id AS user_id,
                       a.real_filename, a.physical_filename, a.mimetype, a.extension
                FROM ' . ATTACHMENTS_TABLE . ' a
                LEFT JOIN ' . $this->flags_table . ' ai
                    ON ai.attach_id = a.attach_id
                WHERE a.is_orphan = 0
                  AND a.in_message = 0
                  AND (
                    a.mimetype LIKE \'image/%\'
                    OR a.extension IN (\'jpg\', \'jpeg\', \'png\', \'gif\', \'webp\', \'bmp\', \'avif\', \'heic\', \'heif\', \'tif\', \'tiff\')
                  )
                  AND (ai.attach_id IS NULL OR ai.scan_status = \'\')
                ORDER BY a.attach_id ASC';
        $result = $this->db->sql_query_limit($sql, (int) $limit);
        $rows = [];
        while ($row = $this->db->sql_fetchrow($result)) {
            $rows[] = $row;
        }
        $this->db->sql_freeresult($result);
        return $rows;
    }
}
