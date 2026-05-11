<?php
/**
 * Slate Ops - Resource Hub.
 *
 * Server-rendered resource library with vanilla JS enhancements for search,
 * tabs, detail view, and the local review queue.
 */
if (!defined('ABSPATH')) exit;

if (!slate_ops_current_user_can_access_ops_page('resource-hub')) {
  ?>
  <section style="display:grid;place-items:center;height:100%;padding:32px;">
    <div style="max-width:520px;text-align:center;background:#fff;border:1px solid #D9D5C7;border-radius:10px;padding:28px;">
      <h1 style="margin:0 0 8px;font-size:24px;">Access denied</h1>
      <p style="margin:0;color:#5E646B;">You do not have permission to view this Slate Ops page.</p>
    </div>
  </section>
  <?php
  return;
}

$can_review = Slate_Ops_Utils::can_supervisor_or_admin();

$resources = [
  [
    'id' => 'sp0114b',
    'sku' => 'SP0114B',
    'title' => 'Sprinter standard roof rack - 144 HR',
    'vendor' => 'Flatline Van Co',
    'doc_type' => 'Install guide',
    'chassis' => 'Sprinter 144 HR',
    'updated_label' => 'Updated Apr 28',
    'updated_date' => '2026-04-28',
    'status' => 'Reviewed by Slate',
    'status_key' => 'reviewed',
    'source_type' => 'vendor',
    'vendor_revision' => 'Rev. D - 2026-03-04',
    'last_review' => '2026-04-28 - J. Reyes',
    'source' => 'vendor-portal / FL-2026-03',
    'file' => 'SP0114B_install_revD.pdf - 4.2 MB - 18 pages',
    'notes' => [
      'Vendor torque spec on the front foot bracket reads 22 ft-lb. On builds before VIN suffix HJ4471, confirm whether the bracket shipped with M10 hardware before torquing.',
      'Vendor diagram p. 6 shows the rear crossbar 18 in. from the slider. Current production is 22 in. Measure off the d-pillar, not the slider track.',
      'Sealant: use 3M 08509 for the 144 HR rain channel.',
    ],
    'attachments' => [
      ['name' => 'Front foot - M10 callout', 'meta' => 'img - 1.4 MB', 'label' => 'Photo - bracket detail'],
      ['name' => 'Crossbar position annotated', 'meta' => 'pdf - 220 KB - 1 page', 'label' => 'Diagram - crossbar'],
      ['name' => 'Rain channel sealant bead', 'meta' => 'img - 980 KB', 'label' => 'Photo - rain channel'],
      ['name' => 'Walkthrough - front bracket', 'meta' => 'mp4 - 12 MB - 0:28', 'label' => 'Video - 28s'],
    ],
    'related' => ['sl-qc-014', 'fl1018', 'sl-crt-007'],
  ],
  [
    'id' => 'aw2231',
    'sku' => 'AW2231',
    'title' => 'Wayfarer bed-mount install - 170 HR',
    'vendor' => 'Adventure Wagon',
    'doc_type' => 'Install guide',
    'chassis' => 'Sprinter 170 HR',
    'updated_label' => 'Updated Apr 22',
    'updated_date' => '2026-04-22',
    'status' => 'Needs Slate review',
    'status_key' => 'needs_review',
    'source_type' => 'vendor',
    'vendor_revision' => 'Rev. 5 - 2026-04-22',
    'last_review' => 'Not reviewed',
    'source' => 'vendor-portal / AW-2026-04',
    'file' => 'AW2231_install_rev5.pdf - 3.8 MB - 14 pages',
    'notes' => [],
    'attachments' => [],
    'related' => ['sp0114b', 'aw1988'],
  ],
  [
    'id' => 'sl-qc-018',
    'sku' => 'SL-QC-018',
    'title' => 'Pre-delivery QC checklist - Transit AWD',
    'vendor' => 'Slate-authored',
    'doc_type' => 'QC checklist',
    'chassis' => 'Transit AWD',
    'updated_label' => 'Updated Apr 19',
    'updated_date' => '2026-04-19',
    'status' => 'Current',
    'status_key' => 'current',
    'source_type' => 'slate',
    'vendor_revision' => 'Slate rev. 3',
    'last_review' => '2026-04-19 - M. Chen',
    'source' => 'Slate engineering',
    'file' => 'SL-QC-018_transit_awd.pdf - 680 KB - 6 pages',
    'notes' => ['Use this checklist after all electrical closeout photos are attached to the job record.'],
    'attachments' => [],
    'related' => ['sl-qc-014'],
  ],
  [
    'id' => 'fl0902',
    'sku' => 'FL0902',
    'title' => '144 HR rear cabinet - spec sheet rev. C',
    'vendor' => 'Flatline Van Co',
    'doc_type' => 'Spec sheet',
    'chassis' => 'Sprinter 144 HR',
    'updated_label' => 'Updated Apr 14',
    'updated_date' => '2026-04-14',
    'status' => 'Reviewed by Slate',
    'status_key' => 'reviewed',
    'source_type' => 'vendor',
    'vendor_revision' => 'Rev. C - 2026-04-14',
    'last_review' => '2026-04-16 - J. Reyes',
    'source' => 'vendor-portal / FL-2026-04',
    'file' => 'FL0902_spec_revC.pdf - 1.1 MB - 5 pages',
    'notes' => ['Use the Slate fastener kit listed in the related build note, not the vendor default pack.'],
    'attachments' => [],
    'related' => ['sp0114b', 'fl1018'],
  ],
  [
    'id' => 'sl-crt-007',
    'sku' => 'SL-CRT-007',
    'title' => 'FMVSS 302 flame certification - upholstery batch 24-A',
    'vendor' => 'Slate-authored',
    'doc_type' => 'Cert',
    'chassis' => 'All chassis',
    'updated_label' => 'Updated Apr 09',
    'updated_date' => '2026-04-09',
    'status' => 'Current',
    'status_key' => 'current',
    'source_type' => 'slate',
    'vendor_revision' => 'Slate rev. 1',
    'last_review' => '2026-04-09 - M. Chen',
    'source' => 'Slate compliance',
    'file' => 'SL-CRT-007_fmvss302.pdf - 420 KB - 3 pages',
    'notes' => ['Attach this cert packet to any pickup packet using upholstery batch 24-A.'],
    'attachments' => [],
    'related' => ['sl-qc-018'],
  ],
  [
    'id' => 'aw2107',
    'sku' => 'AW2107',
    'title' => 'ProMaster overhead cabinet - install rev. 4',
    'vendor' => 'Adventure Wagon',
    'doc_type' => 'Install guide',
    'chassis' => 'ProMaster 159 HR',
    'updated_label' => 'Updated Apr 02',
    'updated_date' => '2026-04-02',
    'status' => 'Reviewed by Slate',
    'status_key' => 'reviewed',
    'source_type' => 'vendor',
    'vendor_revision' => 'Rev. 4 - 2026-04-02',
    'last_review' => '2026-04-05 - J. Reyes',
    'source' => 'vendor-portal / AW-2026-04',
    'file' => 'AW2107_install_rev4.pdf - 2.7 MB - 12 pages',
    'notes' => ['Use production shim pack B on passenger-side upper rail.'],
    'attachments' => [],
    'related' => [],
  ],
  [
    'id' => 'sds-lio-003',
    'sku' => 'SDS-LIO-003',
    'title' => 'Lithium house battery - safety data sheet',
    'vendor' => 'Adventure Wagon',
    'doc_type' => 'SDS',
    'chassis' => 'All chassis',
    'updated_label' => 'Updated Mar 28',
    'updated_date' => '2026-03-28',
    'status' => 'Reviewed by Slate',
    'status_key' => 'reviewed',
    'source_type' => 'vendor',
    'vendor_revision' => 'Rev. 2 - 2026-03-28',
    'last_review' => '2026-03-29 - M. Chen',
    'source' => 'vendor-portal / AW-2026-03',
    'file' => 'SDS-LIO-003.pdf - 510 KB - 8 pages',
    'notes' => ['Keep this SDS available for battery installs and transport questions.'],
    'attachments' => [],
    'related' => [],
  ],
  [
    'id' => 'fl1018',
    'sku' => 'FL1018',
    'title' => '170 HR slider window cutout - template',
    'vendor' => 'Flatline Van Co',
    'doc_type' => 'Spec sheet',
    'chassis' => 'Sprinter 170 HR',
    'updated_label' => 'Updated Mar 22',
    'updated_date' => '2026-03-22',
    'status' => 'Current',
    'status_key' => 'current',
    'source_type' => 'vendor',
    'vendor_revision' => 'Rev. A - 2026-03-22',
    'last_review' => '2026-03-23 - J. Reyes',
    'source' => 'vendor-portal / FL-2026-03',
    'file' => 'FL1018_template.pdf - 900 KB - 2 pages',
    'notes' => ['Print at 100 percent scale and verify the 2 in. reference box before cutting.'],
    'attachments' => [],
    'related' => ['sp0114b'],
  ],
  [
    'id' => 'sl-qc-014',
    'sku' => 'SL-QC-014',
    'title' => 'Electrical pre-delivery QC - 12V house systems',
    'vendor' => 'Slate-authored',
    'doc_type' => 'QC checklist',
    'chassis' => 'All chassis',
    'updated_label' => 'Updated Mar 18',
    'updated_date' => '2026-03-18',
    'status' => 'Reviewed by Slate',
    'status_key' => 'reviewed',
    'source_type' => 'slate',
    'vendor_revision' => 'Slate rev. 6',
    'last_review' => '2026-03-18 - M. Chen',
    'source' => 'Slate engineering',
    'file' => 'SL-QC-014_12v_house_systems.pdf - 740 KB - 7 pages',
    'notes' => ['Run this checklist before the pickup readiness sign-off.'],
    'attachments' => [],
    'related' => ['sl-qc-018'],
  ],
  [
    'id' => 'aw1988',
    'sku' => 'AW1988',
    'title' => '144 HR floor track - torque spec',
    'vendor' => 'Adventure Wagon',
    'doc_type' => 'Spec sheet',
    'chassis' => 'Sprinter 144 HR',
    'updated_label' => 'Updated Mar 11',
    'updated_date' => '2026-03-11',
    'status' => 'Current',
    'status_key' => 'current',
    'source_type' => 'vendor',
    'vendor_revision' => 'Rev. 3 - 2026-03-11',
    'last_review' => '2026-03-12 - J. Reyes',
    'source' => 'vendor-portal / AW-2026-03',
    'file' => 'AW1988_torque_spec.pdf - 330 KB - 2 pages',
    'notes' => ['Use the stainless fastener column for wet-area installs.'],
    'attachments' => [],
    'related' => ['sp0114b'],
  ],
];

$review_queue = array_values(array_filter($resources, function ($resource) {
  return isset($resource['status_key']) && $resource['status_key'] === 'needs_review';
}));

$payload = [
  'resources' => $resources,
  'canReview' => $can_review,
];

$slate_authored_count = count(array_filter($resources, function ($resource) {
  return isset($resource['source_type']) && $resource['source_type'] === 'slate';
}));
$recent_count = count(array_filter($resources, function ($resource) {
  return !empty($resource['updated_date']) && $resource['updated_date'] >= '2026-04-01';
}));
?>
<div class="rh-app" data-can-review="<?php echo $can_review ? '1' : '0'; ?>">
  <script type="application/json" id="slate-resource-hub-data"><?php echo wp_json_encode($payload); ?></script>

  <main class="rh-page">
    <div class="rh-page__head">
      <div>
        <div class="rh-eyebrow">Build floor / shared</div>
        <h1 class="rh-page__title">Resource library</h1>
        <p class="rh-page__sub">Vendor docs, install guides, and Slate-authored references for the build floor.</p>
      </div>
      <div class="rh-page__actions">
        <span class="rh-meta-pill"><?php echo esc_html((string) count($resources)); ?> resources</span>
        <?php if ($can_review) : ?>
          <span class="rh-meta-pill rh-meta-pill--warn"><?php echo esc_html((string) count($review_queue)); ?> need review</span>
          <button class="slate-btn slate-btn--accent slate-btn--sm" type="button" data-rh-add-open>
            <span class="material-symbols-outlined" aria-hidden="true">add</span>
            Add resource
          </button>
        <?php endif; ?>
      </div>
    </div>

    <nav class="rh-tabs" aria-label="Resource Hub sections">
      <button class="rh-tab" type="button" data-rh-tab="library" aria-current="page">Library <span class="rh-tab__count"><?php echo esc_html((string) count($resources)); ?></span></button>
      <button class="rh-tab" type="button" data-rh-tab="slate">Slate-authored <span class="rh-tab__count"><?php echo esc_html((string) $slate_authored_count); ?></span></button>
      <?php if ($can_review) : ?>
        <button class="rh-tab" type="button" data-rh-tab="queue">Admin queue <span class="rh-tab__count"><?php echo esc_html((string) count($review_queue)); ?></span></button>
      <?php endif; ?>
      <button class="rh-tab" type="button" data-rh-tab="recent">Recently updated <span class="rh-tab__count"><?php echo esc_html((string) $recent_count); ?></span></button>
    </nav>

    <section class="rh-library" data-rh-screen="library">
      <div class="rh-sticky">
        <label class="rh-search">
          <span class="material-symbols-outlined rh-search__icon" aria-hidden="true">search</span>
          <input class="rh-search__input" data-rh-search type="search" placeholder="Search SKU, title, vendor..." autocomplete="off">
        </label>
        <div class="rh-chips" role="toolbar" aria-label="Filters">
          <button class="rh-chip" type="button" data-rh-filter="doc_type" data-rh-value="Install guide" aria-pressed="false">Install guide <span class="rh-chip__caret">+</span></button>
          <button class="rh-chip" type="button" data-rh-filter="doc_type" data-rh-value="QC checklist" aria-pressed="false">QC checklist <span class="rh-chip__caret">+</span></button>
          <button class="rh-chip" type="button" data-rh-filter="chassis" data-rh-value="All chassis" aria-pressed="false">All chassis <span class="rh-chip__caret">+</span></button>
          <button class="rh-chip" type="button" data-rh-filter="status_key" data-rh-value="reviewed" aria-pressed="false">Reviewed by Slate <span class="rh-chip__caret">+</span></button>
          <button class="rh-chip" type="button" data-rh-filter="status_key" data-rh-value="needs_review" aria-pressed="false">Needs review <span class="rh-chip__caret">+</span></button>
        </div>
      </div>

      <div class="rh-list-head" aria-hidden="true">
        <div class="rh-list-head__cell">SKU</div>
        <div class="rh-list-head__cell">Title</div>
        <div class="rh-list-head__cell">Doc type</div>
        <div class="rh-list-head__cell">Chassis</div>
        <div class="rh-list-head__cell">Updated</div>
        <div class="rh-list-head__cell" style="text-align:right">Status</div>
      </div>

      <ul class="rh-list" data-rh-list>
        <?php foreach ($resources as $resource) :
          $status_class = $resource['status_key'] === 'needs_review' ? 'pill--warn' : ($resource['status_key'] === 'reviewed' ? 'pill--info' : 'pill--neutral');
          ?>
          <li class="rh-card" data-rh-resource="<?php echo esc_attr($resource['id']); ?>">
            <div class="rh-card__sku"><?php echo esc_html($resource['sku']); ?></div>
            <div class="rh-card__titlewrap">
              <h3 class="rh-card__title"><?php echo esc_html($resource['title']); ?></h3>
              <div class="rh-card__vendor"><?php echo esc_html($resource['vendor']); ?></div>
            </div>
            <div class="rh-card__pillrow"><span class="rh-pill pill--info"><?php echo esc_html($resource['doc_type']); ?></span></div>
            <div class="rh-card__chassis"><?php echo esc_html($resource['chassis']); ?></div>
            <div class="rh-card__updated"><?php echo esc_html($resource['updated_label']); ?></div>
            <div class="rh-card__review"><span class="rh-pill <?php echo esc_attr($status_class); ?>"><?php if ($resource['status_key'] !== 'current') : ?><span class="rh-pill__dot"></span><?php endif; ?><?php echo esc_html($resource['status']); ?></span></div>
          </li>
        <?php endforeach; ?>
      </ul>

      <section class="rh-empty" data-rh-empty hidden role="status">
        <div class="rh-empty__icon" aria-hidden="true"><span class="material-symbols-outlined">search_off</span></div>
        <h2 class="rh-empty__title">No resources match these filters</h2>
        <p class="rh-empty__sub">Try a different vendor, doc type, or chassis combination, or clear filters to see the full library.</p>
        <div class="rh-row rh-row--wrap">
          <button class="rh-btn rh-btn--primary" type="button" data-rh-clear>Clear filters</button>
          <button class="rh-btn" type="button" data-rh-tab-jump="slate">Browse Slate-authored</button>
        </div>
      </section>
    </section>

    <section class="rh-detail" data-rh-screen="detail" hidden></section>
    <section class="rh-queue" data-rh-screen="queue" hidden></section>
  </main>

  <?php if ($can_review) : ?>
    <div class="rh-overlay" data-rh-add-modal hidden role="dialog" aria-labelledby="rh-add-title" aria-modal="true">
      <div class="rh-modal">
        <div class="rh-modal__head">
          <div>
            <h2 class="rh-modal__title" id="rh-add-title">Add resource</h2>
            <p class="rh-modal__sub">Vendor docs land in the review queue. Slate-authored docs publish straight to the library.</p>
          </div>
          <button class="rh-modal__close" type="button" data-rh-dismiss aria-label="Close">
            <span class="material-symbols-outlined" aria-hidden="true">close</span>
          </button>
        </div>

        <form class="rh-modal__body" data-rh-add-form>
          <div class="rh-field">
            <span class="rh-field__label">Source</span>
            <div class="rh-segment" role="group" aria-label="Source">
              <button class="rh-segment__btn" type="button" data-rh-source="vendor" aria-pressed="true">Vendor doc</button>
              <button class="rh-segment__btn" type="button" data-rh-source="slate" aria-pressed="false">Slate-authored</button>
            </div>
            <span class="rh-field__hint" data-rh-source-hint>Vendor doc lands in the review queue with status <em>Needs Slate review</em>.</span>
          </div>

          <div class="rh-field">
            <span class="rh-field__label">File</span>
            <label class="rh-drop" data-rh-drop>
              <input class="rh-file-input" type="file" data-rh-file accept=".pdf,.jpg,.jpeg,.png,.mp4,.mov,image/*,application/pdf,video/*">
              <span class="material-symbols-outlined rh-drop__icon" aria-hidden="true">upload_file</span>
              <span class="rh-drop__title" data-rh-file-title>Drop a PDF, image, or video here</span>
              <span class="rh-drop__sub" data-rh-file-sub>Up to 25 MB - pdf, jpg, png, mp4</span>
              <span class="rh-drop__pick">Choose file</span>
            </label>
          </div>

          <div class="rh-field-grid">
            <div class="rh-field">
              <label class="rh-field__label" for="rh-add-sku">SKU</label>
              <input class="rh-input rh-input--mono" type="text" id="rh-add-sku" name="sku" placeholder="e.g. SP0114B" required>
            </div>
            <div class="rh-field">
              <label class="rh-field__label" for="rh-add-vendor">Vendor</label>
              <select class="rh-select" id="rh-add-vendor" name="vendor">
                <option>Flatline Van Co</option>
                <option>Adventure Wagon</option>
                <option>Owl Vans</option>
                <option>Slate-authored</option>
                <option>Other</option>
              </select>
            </div>
          </div>

          <div class="rh-field-grid">
            <div class="rh-field">
              <label class="rh-field__label" for="rh-add-doctype">Doc type</label>
              <select class="rh-select" id="rh-add-doctype" name="doc_type">
                <option>Install guide</option>
                <option>Spec sheet</option>
                <option>QC checklist</option>
                <option>Cert</option>
                <option>SDS</option>
              </select>
            </div>
            <div class="rh-field">
              <label class="rh-field__label" for="rh-add-rev">Vendor revision</label>
              <input class="rh-input rh-input--mono" type="text" id="rh-add-rev" name="vendor_revision" placeholder="Rev. D - 2026-03-04">
            </div>
          </div>

          <div class="rh-field">
            <span class="rh-field__label">Chassis applicability</span>
            <div class="rh-multi" role="group" aria-label="Chassis applicability">
              <button class="rh-multi__chip" type="button" data-rh-chassis="Sprinter 144 HR" aria-pressed="true">Sprinter 144 HR <span class="rh-multi__chip__x">x</span></button>
              <button class="rh-multi__chip" type="button" data-rh-chassis="Sprinter 170 HR" aria-pressed="false">Sprinter 170 HR</button>
              <button class="rh-multi__chip" type="button" data-rh-chassis="Transit AWD" aria-pressed="false">Transit AWD</button>
              <button class="rh-multi__chip" type="button" data-rh-chassis="ProMaster 159 HR" aria-pressed="false">ProMaster 159 HR</button>
              <button class="rh-multi__chip" type="button" data-rh-chassis="All chassis" aria-pressed="false">All chassis</button>
            </div>
          </div>

          <div class="rh-field">
            <label class="rh-field__label" for="rh-add-title-input">Title</label>
            <input class="rh-input" type="text" id="rh-add-title-input" name="title" placeholder="e.g. Sprinter standard roof rack - 144 HR" required>
          </div>

          <div class="rh-field">
            <label class="rh-field__label" for="rh-add-notes">Initial Slate notes <span class="rh-mute" style="font-weight:500;letter-spacing:0;text-transform:none">(optional)</span></label>
            <textarea class="rh-textarea" id="rh-add-notes" name="notes" placeholder="What should techs watch for? Hardware quirks, vendor errata, sealant call-outs..."></textarea>
          </div>
        </form>

        <div class="rh-modal__foot">
          <button class="rh-btn rh-btn--ghost" type="button" data-rh-dismiss>Cancel</button>
          <div class="rh-row">
            <button class="rh-btn" type="button" data-rh-save-draft>Save draft</button>
            <button class="rh-btn rh-btn--primary" type="button" data-rh-add-submit>Add to queue</button>
          </div>
        </div>
      </div>
    </div>

    <div class="rh-drawer-overlay" data-rh-review-drawer hidden role="dialog" aria-labelledby="rh-review-title" aria-modal="true">
      <aside class="rh-drawer">
        <div class="rh-drawer__head">
          <div class="rh-drawer__head-info">
            <span class="rh-drawer__sku" data-rh-review-sku></span>
            <h2 class="rh-drawer__title" id="rh-review-title" data-rh-review-title></h2>
            <div class="rh-drawer__pillrow" data-rh-review-pills></div>
          </div>
          <button class="rh-modal__close" type="button" data-rh-dismiss aria-label="Close drawer">
            <span class="material-symbols-outlined" aria-hidden="true">close</span>
          </button>
        </div>
        <div class="rh-drawer__body" data-rh-review-body></div>
        <div class="rh-drawer__foot">
          <button class="rh-btn rh-btn--ghost" type="button" data-rh-dismiss>Cancel</button>
          <div class="rh-drawer__foot-right">
            <button class="rh-btn" type="button" data-rh-review-save>Save draft</button>
            <button class="rh-btn" type="button" data-rh-request-changes>Request changes from vendor</button>
            <button class="rh-btn rh-btn--primary" type="button" data-rh-review-approve>Approve and publish</button>
          </div>
        </div>
      </aside>
    </div>
  <?php endif; ?>
</div>
