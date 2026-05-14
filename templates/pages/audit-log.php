<?php
/**
 * Slate Ops — Audit Log page.
 *
 * Read-only admin timeline backed by wp_slate_ops_audit_log.
 */
if (!defined('ABSPATH')) exit;
?>
<section class="ops-audit-log" aria-labelledby="ops-audit-title">
  <header class="ops-audit-log__header">
    <div>
      <h1 id="ops-audit-title">Audit log</h1>
      <p>Review system activity, role changes, job edits, timer events, and admin updates.</p>
    </div>
  </header>

  <div class="ops-audit-summary" aria-label="Audit summary">
    <div class="ops-audit-summary__tile">
      <span>Total events</span>
      <strong id="audit-summary-total">0</strong>
    </div>
    <div class="ops-audit-summary__tile">
      <span>Job changes</span>
      <strong id="audit-summary-jobs">0</strong>
    </div>
    <div class="ops-audit-summary__tile">
      <span>User changes</span>
      <strong id="audit-summary-users">0</strong>
    </div>
    <div class="ops-audit-summary__tile">
      <span>System updates</span>
      <strong id="audit-summary-system">0</strong>
    </div>
    <div class="ops-audit-summary__tile">
      <span>High-signal</span>
      <strong id="audit-summary-high-signal">0</strong>
    </div>
  </div>

  <section class="ops-audit-panel" aria-label="Audit filters">
    <form id="audit-filter-form" class="ops-audit-filters">
      <label>
        <span>Search</span>
        <input id="audit-search" type="search" placeholder="SO, customer, field, note, user" autocomplete="off">
      </label>
      <label>
        <span>From</span>
        <input id="audit-date-from" type="date">
      </label>
      <label>
        <span>To</span>
        <input id="audit-date-to" type="date">
      </label>
      <label>
        <span>Area</span>
        <select id="audit-entity">
          <option value="all">All areas</option>
        </select>
      </label>
      <label>
        <span>Action</span>
        <select id="audit-action">
          <option value="all">All actions</option>
        </select>
      </label>
      <label>
        <span>User</span>
        <select id="audit-user">
          <option value="0">All users</option>
        </select>
      </label>
      <div class="ops-audit-filters__actions">
        <button class="ops-audit-button ops-audit-button--primary" type="submit">
          <span class="material-symbols-outlined" aria-hidden="true">filter_alt</span>
          Apply
        </button>
        <button class="ops-audit-button" id="audit-reset" type="button">
          <span class="material-symbols-outlined" aria-hidden="true">restart_alt</span>
          Reset
        </button>
      </div>
    </form>
  </section>

  <section class="ops-audit-panel ops-audit-panel--table" aria-label="Audit events">
    <div class="ops-audit-table-wrap">
      <table class="ops-audit-table">
        <thead>
          <tr>
            <th>Time</th>
            <th>User</th>
            <th>Area</th>
            <th>Action</th>
            <th>Target</th>
            <th>Change</th>
            <th>Note</th>
          </tr>
        </thead>
        <tbody id="audit-log-body">
          <tr>
            <td colspan="7" class="ops-audit-empty">Loading audit events.</td>
          </tr>
        </tbody>
      </table>
    </div>
    <footer class="ops-audit-pagination">
      <button class="ops-audit-button" id="audit-prev" type="button">
        <span class="material-symbols-outlined" aria-hidden="true">chevron_left</span>
        Previous
      </button>
      <span id="audit-page-status">Page 1 of 1</span>
      <button class="ops-audit-button" id="audit-next" type="button">
        Next
        <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
      </button>
    </footer>
  </section>
</section>

<aside class="ops-audit-drawer" id="audit-detail-drawer" aria-hidden="true" aria-label="Audit event details">
  <div class="ops-audit-drawer__scrim" data-audit-close></div>
  <div class="ops-audit-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="audit-detail-title">
    <header>
      <div>
        <span id="audit-detail-kicker">Audit event</span>
        <h2 id="audit-detail-title">Event details</h2>
      </div>
      <button class="ops-audit-icon-button" type="button" data-audit-close aria-label="Close audit event details">
        <span class="material-symbols-outlined" aria-hidden="true">close</span>
      </button>
    </header>
    <div id="audit-detail-body" class="ops-audit-drawer__body"></div>
  </div>
</aside>
