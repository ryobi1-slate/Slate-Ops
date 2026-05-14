<?php
if (!defined('ABSPATH')) exit;

class Slate_Ops_Resource_Hub {

  const STATUSES = ['draft', 'needs_review', 'reviewed', 'current', 'archived'];
  const AUDIENCES = ['tech', 'cs', 'slate_sales', 'dealer_sales'];
  const LINK_TYPES = ['sales_order', 'rvia', 'bom', 'bom_revision', 'slate_part', 'vendor_part', 'vendor', 'chassis', 'product'];
  const NOTE_TYPES = ['tip', 'issue', 'correction', 'missing_instruction', 'better_photo', 'qc_callout'];
  const NOTE_STATUSES = ['submitted', 'in_review', 'promoted', 'archived'];

  public static function table() {
    global $wpdb;
    return $wpdb->prefix . 'slate_ops_resources';
  }

  public static function links_table() {
    global $wpdb;
    return $wpdb->prefix . 'slate_ops_resource_links';
  }

  public static function field_notes_table() {
    global $wpdb;
    return $wpdb->prefix . 'slate_ops_resource_field_notes';
  }

  public static function table_exists() {
    global $wpdb;
    $table = self::table();
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
  }

  public static function links_table_exists() {
    global $wpdb;
    $table = self::links_table();
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
  }

  public static function field_notes_table_exists() {
    global $wpdb;
    $table = self::field_notes_table();
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
    if (!Slate_Ops_Utils::can_cs_or_above()) {
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
    return self::attach_links(array_map([__CLASS__, 'shape_row'], $rows ?: []));
  }

  public static function get($id) {
    if (!self::table_exists()) {
      return null;
    }

    global $wpdb;
    $table = self::table();
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE resource_id = %d", (int) $id), ARRAY_A);
    if (!$row) {
      return null;
    }
    $shaped = self::attach_links([self::shape_row($row)]);
    return $shaped[0] ?? self::shape_row($row);
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
    if (self::links_table_exists()) {
      self::replace_links($id, $input['links'] ?? self::links_from_resource_input($input));
    }
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
    if (array_key_exists('links', $input) && self::links_table_exists()) {
      self::replace_links((int) $id, $input['links']);
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

  public static function create_field_note($input) {
    if (!self::field_notes_table_exists()) {
      return new WP_Error('field_notes_table_missing', 'Resource Hub field notes table is not installed.', ['status' => 500]);
    }

    global $wpdb;
    $table = self::field_notes_table();
    $now = Slate_Ops_Utils::now_gmt();
    $row = [
      'note_type'       => self::sanitize_note_type($input['note_type'] ?? 'tip'),
      'status_key'      => 'submitted',
      'title'           => sanitize_text_field($input['title'] ?? ''),
      'note_body'       => sanitize_textarea_field($input['note_body'] ?? ''),
      'job_id'          => !empty($input['job_id']) ? (int) $input['job_id'] : null,
      'so_number'       => sanitize_text_field($input['so_number'] ?? ''),
      'rvia_no'         => sanitize_text_field($input['rvia_no'] ?? ''),
      'build_type'      => sanitize_text_field($input['build_type'] ?? ''),
      'bom_no'          => sanitize_text_field($input['bom_no'] ?? ''),
      'bom_revision'    => sanitize_text_field($input['bom_revision'] ?? ''),
      'slate_part_no'   => sanitize_text_field($input['slate_part_no'] ?? ''),
      'vendor_part_no'  => sanitize_text_field($input['vendor_part_no'] ?? ''),
      'vendor'          => sanitize_text_field($input['vendor'] ?? ''),
      'chassis'         => sanitize_text_field($input['chassis'] ?? ''),
      'media_name'      => sanitize_text_field($input['media_name'] ?? ''),
      'media_url'       => esc_url_raw($input['media_url'] ?? ''),
      'media_meta'      => sanitize_text_field($input['media_meta'] ?? ''),
      'submitted_by'    => get_current_user_id() ?: null,
      'reviewed_by'     => null,
      'promoted_resource_id' => null,
      'created_at'      => $now,
      'updated_at'      => $now,
      'reviewed_at'     => null,
    ];

    if ($row['title'] === '' || $row['note_body'] === '') {
      return new WP_Error('invalid_field_note', 'Title and note are required.', ['status' => 400]);
    }

    $inserted = $wpdb->insert($table, $row);
    if (!$inserted) {
      return new WP_Error('field_note_insert_failed', 'Could not save the field note.', ['status' => 500]);
    }

    $id = (int) $wpdb->insert_id;
    Slate_Ops_Activity_Log::append('resource_field_note', $id, 'created', $row);
    return self::get_field_note($id);
  }

  public static function list_field_notes($status = '') {
    if (!self::field_notes_table_exists()) {
      return [];
    }

    global $wpdb;
    $table = self::field_notes_table();
    $status = sanitize_key((string) $status);
    if ($status !== '' && in_array($status, self::NOTE_STATUSES, true)) {
      $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE status_key = %s ORDER BY created_at DESC, note_id DESC", $status), ARRAY_A);
    } else {
      $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC, note_id DESC", ARRAY_A);
    }
    return array_map([__CLASS__, 'shape_field_note'], $rows ?: []);
  }

  public static function get_field_note($id) {
    if (!self::field_notes_table_exists()) {
      return null;
    }

    global $wpdb;
    $table = self::field_notes_table();
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE note_id = %d", (int) $id), ARRAY_A);
    return $row ? self::shape_field_note($row) : null;
  }

  public static function update_field_note($id, $input) {
    if (!self::field_notes_table_exists()) {
      return new WP_Error('field_notes_table_missing', 'Resource Hub field notes table is not installed.', ['status' => 500]);
    }

    global $wpdb;
    $table = self::field_notes_table();
    $existing = self::get_field_note($id);
    if (!$existing) {
      return new WP_Error('field_note_not_found', 'Field note not found.', ['status' => 404]);
    }

    $update = [
      'updated_at' => Slate_Ops_Utils::now_gmt(),
    ];
    foreach (['title', 'note_body', 'so_number', 'rvia_no', 'build_type', 'bom_no', 'bom_revision', 'slate_part_no', 'vendor_part_no', 'vendor', 'chassis', 'media_name', 'media_meta'] as $field) {
      if (array_key_exists($field, $input)) {
        $update[$field] = $field === 'note_body'
          ? sanitize_textarea_field($input[$field] ?? '')
          : sanitize_text_field($input[$field] ?? '');
      }
    }
    if (array_key_exists('job_id', $input)) {
      $update['job_id'] = !empty($input['job_id']) ? (int) $input['job_id'] : null;
    }
    if (array_key_exists('media_url', $input)) {
      $update['media_url'] = esc_url_raw($input['media_url'] ?? '');
    }
    if (array_key_exists('note_type', $input)) {
      $update['note_type'] = self::sanitize_note_type($input['note_type']);
    }
    if (array_key_exists('status_key', $input)) {
      $update['status_key'] = self::sanitize_note_status($input['status_key']);
      $update['reviewed_by'] = get_current_user_id() ?: null;
      $update['reviewed_at'] = Slate_Ops_Utils::now_gmt();
    }

    $ok = $wpdb->update($table, $update, ['note_id' => (int) $id]);
    if ($ok === false) {
      return new WP_Error('field_note_update_failed', 'Could not update the field note.', ['status' => 500]);
    }

    Slate_Ops_Activity_Log::append('resource_field_note', (int) $id, 'updated', $update, $existing);
    return self::get_field_note($id);
  }

  public static function promote_field_note($id, $input = []) {
    $note = self::get_field_note($id);
    if (!$note) {
      return new WP_Error('field_note_not_found', 'Field note not found.', ['status' => 404]);
    }

    $resource = self::create([
      'sku' => $input['sku'] ?? self::first_non_empty([$note['slate_part_no'], $note['vendor_part_no'], $note['bom_no'], 'FIELD-NOTE-' . $note['id']]),
      'title' => $input['title'] ?? $note['title'],
      'vendor' => $input['vendor'] ?? ($note['vendor'] ?: 'Slate-authored'),
      'doc_type' => $input['doc_type'] ?? 'Install note',
      'chassis' => $input['chassis'] ?? ($note['chassis'] ?: 'All chassis'),
      'audience' => $input['audience'] ?? ['tech', 'cs'],
      'status_key' => 'reviewed',
      'source_type' => 'slate',
      'vendor_revision' => $input['vendor_revision'] ?? ($note['bom_revision'] ?: ''),
      'last_review' => gmdate('Y-m-d') . ' - ' . Slate_Ops_Utils::user_display(get_current_user_id()),
      'source' => 'Tech field note',
      'file_name' => $note['media_name'],
      'file_url' => $note['media_url'],
      'file_meta' => $note['media_meta'],
      'notes' => [$note['note_body']],
      'attachments' => self::field_note_attachments($note),
      'related' => [],
      'links' => self::links_from_field_note($note),
    ]);

    if (is_wp_error($resource)) {
      return $resource;
    }

    global $wpdb;
    $table = self::field_notes_table();
    $update = [
      'status_key' => 'promoted',
      'promoted_resource_id' => (int) $resource['id'],
      'reviewed_by' => get_current_user_id() ?: null,
      'reviewed_at' => Slate_Ops_Utils::now_gmt(),
      'updated_at' => Slate_Ops_Utils::now_gmt(),
    ];
    $wpdb->update($table, $update, ['note_id' => (int) $id]);
    Slate_Ops_Activity_Log::append('resource_field_note', (int) $id, 'promoted', $update, $note);

    return [
      'note' => self::get_field_note($id),
      'resource' => $resource,
    ];
  }

  public static function resources_for_context($link_type, $link_key) {
    if (!self::links_table_exists() || !self::table_exists()) {
      return [];
    }

    $link_type = self::sanitize_link_type($link_type);
    $link_key = sanitize_text_field($link_key);
    if ($link_key === '') {
      return [];
    }

    global $wpdb;
    $resources = self::table();
    $links = self::links_table();
    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT r.* FROM $resources r INNER JOIN $links l ON r.resource_id = l.resource_id WHERE l.link_type = %s AND l.link_key = %s ORDER BY r.updated_at DESC, r.resource_id DESC",
      $link_type,
      $link_key
    ), ARRAY_A);

    return self::attach_links(array_map([__CLASS__, 'shape_row'], $rows ?: []));
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
      'links'           => [],
    ];
  }

  private static function attach_links($resources) {
    if (empty($resources) || !self::links_table_exists()) {
      return $resources;
    }

    global $wpdb;
    $table = self::links_table();
    $ids = array_values(array_filter(array_map(function ($resource) {
      return isset($resource['id']) ? (int) $resource['id'] : 0;
    }, $resources)));
    if (empty($ids)) {
      return $resources;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE resource_id IN ($placeholders) ORDER BY link_id ASC", $ids), ARRAY_A);
    $by_resource = [];
    foreach ($rows ?: [] as $row) {
      $resource_id = (string) (int) ($row['resource_id'] ?? 0);
      if (!isset($by_resource[$resource_id])) $by_resource[$resource_id] = [];
      $by_resource[$resource_id][] = self::shape_link($row);
    }

    foreach ($resources as &$resource) {
      $resource['links'] = $by_resource[(string) (int) $resource['id']] ?? [];
    }
    unset($resource);
    return $resources;
  }

  private static function replace_links($resource_id, $links) {
    global $wpdb;
    $table = self::links_table();
    $resource_id = (int) $resource_id;
    $wpdb->delete($table, ['resource_id' => $resource_id]);

    foreach (self::sanitize_links($links) as $link) {
      $wpdb->insert($table, [
        'resource_id' => $resource_id,
        'link_type' => $link['link_type'],
        'link_key' => $link['link_key'],
        'link_label' => $link['link_label'],
        'source_system' => $link['source_system'],
        'created_by' => get_current_user_id() ?: null,
        'created_at' => Slate_Ops_Utils::now_gmt(),
      ]);
    }
  }

  private static function sanitize_links($links) {
    $items = is_array($links) ? $links : [];
    $out = [];
    foreach ($items as $item) {
      if (!is_array($item)) continue;
      $type = self::sanitize_link_type($item['link_type'] ?? '');
      $key = sanitize_text_field($item['link_key'] ?? '');
      if ($key === '') continue;
      $out[] = [
        'link_type' => $type,
        'link_key' => $key,
        'link_label' => sanitize_text_field($item['link_label'] ?? $key),
        'source_system' => self::sanitize_source_system($item['source_system'] ?? 'manual'),
      ];
    }
    return $out;
  }

  private static function links_from_resource_input($input) {
    $map = [
      'bom_no' => 'bom',
      'bom_revision' => 'bom_revision',
      'slate_part_no' => 'slate_part',
      'vendor_part_no' => 'vendor_part',
    ];
    $links = [];
    foreach ($map as $field => $type) {
      $value = sanitize_text_field($input[$field] ?? '');
      if ($value !== '') {
        $links[] = [
          'link_type' => $type,
          'link_key' => $value,
          'link_label' => $value,
          'source_system' => $type === 'bom' || $type === 'bom_revision' ? 'bc' : 'manual',
        ];
      }
    }
    return $links;
  }

  private static function links_from_field_note($note) {
    $links = [];
    $pairs = [
      ['sales_order', 'so_number', 'slate'],
      ['rvia', 'rvia_no', 'slate'],
      ['bom', 'bom_no', 'bc'],
      ['bom_revision', 'bom_revision', 'bc'],
      ['slate_part', 'slate_part_no', 'manual'],
      ['vendor_part', 'vendor_part_no', 'manual'],
      ['vendor', 'vendor', 'manual'],
      ['chassis', 'chassis', 'manual'],
    ];
    foreach ($pairs as $pair) {
      $value = sanitize_text_field($note[$pair[1]] ?? '');
      if ($value === '') continue;
      $links[] = [
        'link_type' => $pair[0],
        'link_key' => $value,
        'link_label' => $value,
        'source_system' => $pair[2],
      ];
    }
    return $links;
  }

  private static function shape_link($row) {
    return [
      'id' => (string) ($row['link_id'] ?? ''),
      'resource_id' => (string) ($row['resource_id'] ?? ''),
      'link_type' => (string) ($row['link_type'] ?? ''),
      'link_key' => (string) ($row['link_key'] ?? ''),
      'link_label' => (string) ($row['link_label'] ?? ''),
      'source_system' => (string) ($row['source_system'] ?? 'manual'),
    ];
  }

  private static function shape_field_note($row) {
    $submitted_by = !empty($row['submitted_by']) ? (int) $row['submitted_by'] : 0;
    $reviewed_by = !empty($row['reviewed_by']) ? (int) $row['reviewed_by'] : 0;
    return [
      'id' => (string) ($row['note_id'] ?? ''),
      'note_type' => (string) ($row['note_type'] ?? 'tip'),
      'note_type_label' => self::note_type_label((string) ($row['note_type'] ?? 'tip')),
      'status_key' => (string) ($row['status_key'] ?? 'submitted'),
      'status' => self::note_status_label((string) ($row['status_key'] ?? 'submitted')),
      'title' => (string) ($row['title'] ?? ''),
      'note_body' => (string) ($row['note_body'] ?? ''),
      'job_id' => (string) ($row['job_id'] ?? ''),
      'so_number' => (string) ($row['so_number'] ?? ''),
      'rvia_no' => (string) ($row['rvia_no'] ?? ''),
      'build_type' => (string) ($row['build_type'] ?? ''),
      'bom_no' => (string) ($row['bom_no'] ?? ''),
      'bom_revision' => (string) ($row['bom_revision'] ?? ''),
      'slate_part_no' => (string) ($row['slate_part_no'] ?? ''),
      'vendor_part_no' => (string) ($row['vendor_part_no'] ?? ''),
      'vendor' => (string) ($row['vendor'] ?? ''),
      'chassis' => (string) ($row['chassis'] ?? ''),
      'media_name' => (string) ($row['media_name'] ?? ''),
      'media_url' => (string) ($row['media_url'] ?? ''),
      'media_meta' => (string) ($row['media_meta'] ?? ''),
      'submitted_by' => $submitted_by ? Slate_Ops_Utils::user_display($submitted_by) : '',
      'reviewed_by' => $reviewed_by ? Slate_Ops_Utils::user_display($reviewed_by) : '',
      'promoted_resource_id' => (string) ($row['promoted_resource_id'] ?? ''),
      'created_at' => (string) ($row['created_at'] ?? ''),
      'updated_at' => (string) ($row['updated_at'] ?? ''),
      'reviewed_at' => (string) ($row['reviewed_at'] ?? ''),
    ];
  }

  private static function field_note_attachments($note) {
    if (empty($note['media_name'])) {
      return [];
    }
    return [[
      'name' => $note['media_name'],
      'meta' => $note['media_meta'],
      'label' => 'Field note media',
      'url' => $note['media_url'],
    ]];
  }

  private static function first_non_empty($values) {
    foreach ($values as $value) {
      $value = trim((string) $value);
      if ($value !== '') return $value;
    }
    return '';
  }

  private static function current_user_audiences() {
    $audiences = [];
    if (Slate_Ops_Utils::is_tech()) $audiences[] = 'tech';
    if (Slate_Ops_Utils::is_cs()) $audiences[] = 'cs';
    if (Slate_Ops_Utils::can_cs_or_above()) $audiences = self::AUDIENCES;
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

  private static function sanitize_link_type($type) {
    $type = sanitize_key((string) $type);
    return in_array($type, self::LINK_TYPES, true) ? $type : 'slate_part';
  }

  private static function sanitize_source_system($source_system) {
    $source_system = sanitize_key((string) $source_system);
    return in_array($source_system, ['bc', 'slate', 'vendor', 'manual'], true) ? $source_system : 'manual';
  }

  private static function sanitize_note_type($type) {
    $type = sanitize_key((string) $type);
    return in_array($type, self::NOTE_TYPES, true) ? $type : 'tip';
  }

  private static function sanitize_note_status($status) {
    $status = sanitize_key((string) $status);
    return in_array($status, self::NOTE_STATUSES, true) ? $status : 'submitted';
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

  private static function note_type_label($type) {
    $labels = [
      'tip' => 'Tip',
      'issue' => 'Issue',
      'correction' => 'Correction',
      'missing_instruction' => 'Missing instruction',
      'better_photo' => 'Better photo',
      'qc_callout' => 'QC callout',
    ];
    return $labels[$type] ?? 'Tip';
  }

  private static function note_status_label($status) {
    $labels = [
      'submitted' => 'Submitted',
      'in_review' => 'In review',
      'promoted' => 'Promoted',
      'archived' => 'Archived',
    ];
    return $labels[$status] ?? 'Submitted';
  }
}
