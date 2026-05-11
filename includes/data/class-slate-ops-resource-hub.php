<?php
if (!defined('ABSPATH')) exit;

class Slate_Ops_Resource_Hub {

  const STATUSES = ['draft', 'needs_review', 'reviewed', 'current', 'archived'];
  const AUDIENCES = ['tech', 'cs', 'slate_sales', 'dealer_sales'];

  public static function table() {
    global $wpdb;
    return $wpdb->prefix . 'slate_ops_resources';
  }

  public static function table_exists() {
    global $wpdb;
    $table = self::table();
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
  }

  public static function maybe_seed($defaults = []) {
    if (!self::table_exists()) {
      return;
    }

    global $wpdb;
    $table = self::table();
    $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    if ($count > 0 || empty($defaults) || !is_array($defaults)) {
      return;
    }

    foreach ($defaults as $resource) {
      if (!is_array($resource)) continue;
      self::create(array_merge([
        'audience' => self::default_audience_for($resource),
      ], $resource), true);
    }
  }

  public static function list_for_current_user() {
    if (!self::table_exists()) {
      return [];
    }

    global $wpdb;
    $table = self::table();

    $where = '';
    $params = [];
    if (!Slate_Ops_Utils::can_supervisor_or_admin()) {
      $allowed = self::current_user_audiences();
      $status_placeholders = implode(',', array_fill(0, 2, '%s'));
      $where = "WHERE status_key IN ($status_placeholders)";
      $params = ['reviewed', 'current'];

      if (!empty($allowed)) {
        $audience_sql = [];
        foreach ($allowed as $audience) {
          $audience_sql[] = 'audience LIKE %s';
          $params[] = '%' . $wpdb->esc_like('"' . $audience . '"') . '%';
        }
        $where .= ' AND (' . implode(' OR ', $audience_sql) . ')';
      }
    }

    $sql = "SELECT * FROM $table $where ORDER BY updated_at DESC, resource_id DESC";
    $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
    return array_map([__CLASS__, 'shape_row'], $rows ?: []);
  }

  public static function get($id) {
    if (!self::table_exists()) {
      return null;
    }

    global $wpdb;
    $table = self::table();
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE resource_id = %d", (int) $id), ARRAY_A);
    return $row ? self::shape_row($row) : null;
  }

  public static function create($input, $seed = false) {
    if (!self::table_exists()) {
      return new WP_Error('resource_table_missing', 'Resource Hub table is not installed.', ['status' => 500]);
    }

    global $wpdb;
    $table = self::table();
    $now = Slate_Ops_Utils::now_gmt();
    $source_type = self::sanitize_source_type($input['source_type'] ?? 'vendor');
    $status_key = self::sanitize_status($input['status_key'] ?? ($source_type === 'vendor' ? 'needs_review' : 'reviewed'));
    $resource_key = sanitize_key((string) ($input['id'] ?? $input['resource_key'] ?? ''));
    if ($resource_key === '') {
      $resource_key = sanitize_key((string) ($input['sku'] ?? 'resource') . '-' . wp_generate_password(8, false, false));
    }

    $row = [
      'resource_key'    => $resource_key,
      'sku'             => sanitize_text_field($input['sku'] ?? ''),
      'title'           => sanitize_text_field($input['title'] ?? ''),
      'vendor'          => sanitize_text_field($input['vendor'] ?? ''),
      'doc_type'        => sanitize_text_field($input['doc_type'] ?? ''),
      'chassis'         => sanitize_text_field($input['chassis'] ?? 'All chassis'),
      'audience'        => wp_json_encode(self::sanitize_audience($input['audience'] ?? self::default_audience_for($input))),
      'status_key'      => $status_key,
      'source_type'     => $source_type,
      'vendor_revision' => sanitize_text_field($input['vendor_revision'] ?? ''),
      'last_review'     => sanitize_text_field($input['last_review'] ?? ($status_key === 'needs_review' ? 'Not reviewed' : '')),
      'source'          => sanitize_text_field($input['source'] ?? ''),
      'file_name'       => sanitize_text_field($input['file_name'] ?? $input['file'] ?? ''),
      'file_url'        => esc_url_raw($input['file_url'] ?? ''),
      'file_meta'       => sanitize_text_field($input['file_meta'] ?? ''),
      'notes'           => wp_json_encode(self::sanitize_string_list($input['notes'] ?? [])),
      'attachments'     => wp_json_encode(self::sanitize_attachments($input['attachments'] ?? [])),
      'related'         => wp_json_encode(self::sanitize_string_list($input['related'] ?? [])),
      'created_by'      => $seed ? null : (get_current_user_id() ?: null),
      'updated_by'      => $seed ? null : (get_current_user_id() ?: null),
      'reviewed_by'     => (!$seed && in_array($status_key, ['reviewed', 'current'], true)) ? (get_current_user_id() ?: null) : null,
      'created_at'      => $now,
      'updated_at'      => $now,
      'reviewed_at'     => (!$seed && in_array($status_key, ['reviewed', 'current'], true)) ? $now : null,
    ];

    if ($row['title'] === '' || $row['sku'] === '') {
      return new WP_Error('invalid_resource', 'SKU and title are required.', ['status' => 400]);
    }

    $inserted = $wpdb->insert($table, $row);
    if (!$inserted) {
      return new WP_Error('resource_insert_failed', 'Could not save the resource.', ['status' => 500]);
    }

    $id = (int) $wpdb->insert_id;
    if (!$seed) {
      Slate_Ops_Activity_Log::append('resource', $id, 'created', $row);
    }

    return self::get($id);
  }

  public static function update($id, $input) {
    if (!self::table_exists()) {
      return new WP_Error('resource_table_missing', 'Resource Hub table is not installed.', ['status' => 500]);
    }

    global $wpdb;
    $table = self::table();
    $existing = self::get($id);
    if (!$existing) {
      return new WP_Error('resource_not_found', 'Resource not found.', ['status' => 404]);
    }

    $update = [
      'updated_at' => Slate_Ops_Utils::now_gmt(),
      'updated_by' => get_current_user_id() ?: null,
    ];

    foreach (['sku', 'title', 'vendor', 'doc_type', 'chassis', 'vendor_revision', 'last_review', 'source', 'file_name', 'file_meta'] as $field) {
      if (array_key_exists($field, $input)) {
        $update[$field] = sanitize_text_field($input[$field] ?? '');
      }
    }
    if (array_key_exists('file_url', $input)) {
      $update['file_url'] = esc_url_raw($input['file_url'] ?? '');
    }
    if (array_key_exists('source_type', $input)) {
      $update['source_type'] = self::sanitize_source_type($input['source_type']);
    }
    if (array_key_exists('audience', $input)) {
      $update['audience'] = wp_json_encode(self::sanitize_audience($input['audience']));
    }
    if (array_key_exists('notes', $input)) {
      $update['notes'] = wp_json_encode(self::sanitize_string_list($input['notes']));
    }
    if (array_key_exists('attachments', $input)) {
      $update['attachments'] = wp_json_encode(self::sanitize_attachments($input['attachments']));
    }
    if (array_key_exists('related', $input)) {
      $update['related'] = wp_json_encode(self::sanitize_string_list($input['related']));
    }
    if (array_key_exists('status_key', $input)) {
      $status_key = self::sanitize_status($input['status_key']);
      $update['status_key'] = $status_key;
      if (in_array($status_key, ['reviewed', 'current'], true)) {
        $update['reviewed_at'] = Slate_Ops_Utils::now_gmt();
        $update['reviewed_by'] = get_current_user_id() ?: null;
        $update['last_review'] = gmdate('Y-m-d') . ' - ' . Slate_Ops_Utils::user_display(get_current_user_id());
      }
    }

    $ok = $wpdb->update($table, $update, ['resource_id' => (int) $id]);
    if ($ok === false) {
      return new WP_Error('resource_update_failed', 'Could not update the resource.', ['status' => 500]);
    }

    Slate_Ops_Activity_Log::append('resource', (int) $id, 'updated', $update, $existing);
    return self::get($id);
  }

  private static function shape_row($row) {
    $notes = self::decode_json_list($row['notes'] ?? '[]');
    $attachments = self::decode_json_list($row['attachments'] ?? '[]');
    $related = self::decode_json_list($row['related'] ?? '[]');
    $audience = self::decode_json_list($row['audience'] ?? '[]');
    $updated_date = !empty($row['updated_at']) ? substr((string) $row['updated_at'], 0, 10) : '';

    return [
      'id'              => (string) ($row['resource_id'] ?? ''),
      'resource_key'    => (string) ($row['resource_key'] ?? ''),
      'sku'             => (string) ($row['sku'] ?? ''),
      'title'           => (string) ($row['title'] ?? ''),
      'vendor'          => (string) ($row['vendor'] ?? ''),
      'doc_type'        => (string) ($row['doc_type'] ?? ''),
      'chassis'         => (string) ($row['chassis'] ?? ''),
      'audience'        => $audience,
      'updated_label'   => $updated_date ? 'Updated ' . date_i18n('M d', strtotime($updated_date)) : 'Updated',
      'updated_date'    => $updated_date,
      'status'          => self::status_label((string) ($row['status_key'] ?? 'draft')),
      'status_key'      => (string) ($row['status_key'] ?? 'draft'),
      'source_type'     => (string) ($row['source_type'] ?? 'vendor'),
      'vendor_revision' => (string) ($row['vendor_revision'] ?? ''),
      'last_review'     => (string) ($row['last_review'] ?? ''),
      'source'          => (string) ($row['source'] ?? ''),
      'file'            => (string) ($row['file_name'] ?? ''),
      'file_name'       => (string) ($row['file_name'] ?? ''),
      'file_url'        => (string) ($row['file_url'] ?? ''),
      'file_meta'       => (string) ($row['file_meta'] ?? ''),
      'notes'           => $notes,
      'attachments'     => $attachments,
      'related'         => $related,
    ];
  }

  private static function current_user_audiences() {
    $audiences = [];
    if (Slate_Ops_Utils::is_tech()) $audiences[] = 'tech';
    if (Slate_Ops_Utils::is_cs()) $audiences[] = 'cs';
    if (Slate_Ops_Utils::can_supervisor_or_admin()) $audiences = self::AUDIENCES;
    if (current_user_can(Slate_Ops_Utils::CAP_VIEW_EXECUTIVE)) $audiences[] = 'cs';
    return array_values(array_unique($audiences));
  }

  private static function default_audience_for($resource) {
    $type = isset($resource['doc_type']) ? strtolower((string) $resource['doc_type']) : '';
    if (in_array($type, ['install guide', 'qc checklist', 'sds'], true)) {
      return ['tech', 'cs'];
    }
    if ($type === 'cert') {
      return ['tech', 'cs', 'slate_sales', 'dealer_sales'];
    }
    return ['tech', 'cs', 'slate_sales'];
  }

  private static function sanitize_audience($audience) {
    $items = is_array($audience) ? $audience : explode(',', (string) $audience);
    $out = [];
    foreach ($items as $item) {
      $key = sanitize_key((string) $item);
      if (in_array($key, self::AUDIENCES, true)) $out[] = $key;
    }
    return array_values(array_unique($out ?: ['tech', 'cs']));
  }

  private static function sanitize_status($status) {
    $status = sanitize_key((string) $status);
    return in_array($status, self::STATUSES, true) ? $status : 'draft';
  }

  private static function sanitize_source_type($source_type) {
    $source_type = sanitize_key((string) $source_type);
    return $source_type === 'slate' ? 'slate' : 'vendor';
  }

  private static function sanitize_string_list($items) {
    $items = is_array($items) ? $items : preg_split('/\r\n|\r|\n/', (string) $items);
    $out = [];
    foreach ($items as $item) {
      $text = sanitize_textarea_field((string) $item);
      if ($text !== '') $out[] = $text;
    }
    return $out;
  }

  private static function sanitize_attachments($items) {
    if (!is_array($items)) return [];
    $out = [];
    foreach ($items as $item) {
      if (!is_array($item)) continue;
      $out[] = [
        'name'  => sanitize_text_field($item['name'] ?? ''),
        'meta'  => sanitize_text_field($item['meta'] ?? ''),
        'label' => sanitize_text_field($item['label'] ?? ''),
        'url'   => esc_url_raw($item['url'] ?? ''),
      ];
    }
    return $out;
  }

  private static function decode_json_list($value) {
    $decoded = json_decode((string) $value, true);
    return is_array($decoded) ? $decoded : [];
  }

  private static function status_label($status) {
    $labels = [
      'draft'        => 'Draft',
      'needs_review' => 'Needs Slate review',
      'reviewed'     => 'Reviewed by Slate',
      'current'      => 'Current',
      'archived'     => 'Archived',
    ];
    return $labels[$status] ?? 'Draft';
  }
}
