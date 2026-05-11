<?php
/**
 * Slate Ops - Ops Home.
 *
 * Content-only page template for the bare /ops/ route. The surrounding shell
 * (topbar, sidebar, #ops-view wrapper) is provided by templates/ops-app.php.
 */
if (!defined('ABSPATH')) exit;

$allowed = Slate_Ops_Utils::user_allowed_pages();

$can = function ($slug) use ($allowed) {
  return in_array($slug, $allowed, true);
};

$quick_links = [
  [
    'slug' => 'cs-dashboard',
    'label' => 'CS Workspace',
    'href' => home_url('/ops/cs-dashboard'),
    'icon' => 'support_agent',
    'description' => 'Job intake, queue, scheduling, and tech assignment. The default workspace for customer service.',
    'meta' => 'Queue, intake, scheduling',
  ],
  [
    'slug' => 'tech',
    'label' => 'Tech Screen',
    'href' => home_url('/ops/tech'),
    'icon' => 'build',
    'description' => 'Active job, timers, up next, and QC actions. Optimized for shop floor screens.',
    'meta' => 'Timers, QC, active work',
  ],
  [
    'slug' => 'executive',
    'label' => 'Executive',
    'href' => home_url('/ops/exec'),
    'icon' => 'monitoring',
    'description' => 'Operations overview, throughput, labor capture, and bottleneck performance.',
    'meta' => 'Throughput and exceptions',
  ],
  [
    'slug' => 'resource-hub',
    'label' => 'Resource Hub',
    'href' => home_url('/ops/resource-hub'),
    'icon' => 'menu_book',
    'description' => 'Internal documentation, process references, SOPs, and shared files.',
    'meta' => 'Docs and process references',
  ],
  [
    'slug' => 'monitor',
    'label' => 'Monitor',
    'href' => home_url('/slate-ops-monitor/'),
    'icon' => 'desktop_windows',
    'description' => 'Floor status display for wall-mounted TVs in shop and dispatch areas.',
    'meta' => 'Live floor view',
  ],
  [
    'slug' => 'settings',
    'label' => 'Settings / Admin',
    'href' => home_url('/ops/settings'),
    'icon' => 'tune',
    'description' => 'Role-based page access, integrations, and portal configuration.',
    'meta' => 'Admin controls',
  ],
];

$visible_links = array_values(array_filter($quick_links, function ($link) use ($can) {
  return $can($link['slug']);
}));

$changelog = [
  [
    'when' => 'May 11',
    'version' => 'v0.58.1',
    'title' => 'CS Workspace queue ordering and tech reassignment',
    'description' => 'Queue tools help CS manage intake, assignment, visibility, and notes from one workspace.',
    'tags' => ['CS'],
  ],
  [
    'when' => 'May 9',
    'version' => 'v0.58.0',
    'title' => 'Tech screen timer and QC flow polish',
    'description' => 'The tech surface keeps active work, timers, up next, and QC actions in the shop workflow.',
    'tags' => ['Tech', 'UI'],
  ],
  [
    'when' => 'May 7',
    'version' => 'v0.57.0',
    'title' => 'Resource Hub visual alignment',
    'description' => 'Docs, breadcrumbs, and headers now better match the operational shell.',
    'tags' => ['Docs', 'UI'],
  ],
  [
    'when' => 'May 5',
    'version' => 'v0.56.0',
    'title' => 'Access rules and role-based navigation cleanup',
    'description' => 'Navigation now follows the page access matrix so users only see reachable workspaces.',
    'tags' => ['Admin'],
  ],
];

$guidance = [
  [
    'role' => 'CS',
    'class' => 'cs',
    'text' => 'Run intake, queue, scheduling, customer updates, and tech assignment from CS Workspace.',
  ],
  [
    'role' => 'Techs',
    'class' => 'tech',
    'text' => 'Work from Tech Screen only. Active job, timers, up next, and QC actions live there.',
  ],
  [
    'role' => 'Supervisors',
    'class' => 'supervisor',
    'text' => 'Use Executive for throughput, labor capture, and exceptions. Use Monitor for shared floor visibility.',
  ],
  [
    'role' => 'Admins',
    'class' => 'admin',
    'text' => 'Manage page access and configuration in Settings/Admin. Keep process docs current in Resource Hub.',
  ],
];
?>

<div class="ops-home">
  <div class="ops-home__wrap">
    <header class="ops-home__header">
      <div>
        <div class="ops-home__eyebrow">Operations / Internal</div>
        <h1>Ops Home</h1>
        <p>Quick links, launch notes, and workspace guidance.</p>
      </div>
      <div class="ops-home__meta" aria-label="Ops Home metadata">
        <span class="ops-home__status"><span aria-hidden="true"></span>Updated today</span>
        <span>Build <strong><?php echo esc_html(SLATE_OPS_VERSION); ?></strong></span>
      </div>
    </header>

    <section class="ops-home__section" aria-labelledby="ops-home-quick-links">
      <div class="ops-home__section-head">
        <h2 id="ops-home-quick-links">Quick Links <span><?php echo esc_html(count($visible_links)); ?> available</span></h2>
        <?php if ($can('settings')) : ?>
          <a href="<?php echo esc_url(home_url('/ops/settings')); ?>">Customize <span class="material-symbols-outlined" aria-hidden="true">edit</span></a>
        <?php endif; ?>
      </div>

      <div class="ops-home__quick-grid">
        <?php foreach ($visible_links as $link) : ?>
          <a class="ops-home-link<?php echo $link['slug'] === 'cs-dashboard' ? ' is-primary' : ''; ?>" href="<?php echo esc_url($link['href']); ?>">
            <span class="ops-home-link__icon material-symbols-outlined" aria-hidden="true"><?php echo esc_html($link['icon']); ?></span>
            <span class="ops-home-link__body">
              <span class="ops-home-link__title"><?php echo esc_html($link['label']); ?></span>
              <span class="ops-home-link__path"><?php echo esc_html((string) wp_parse_url($link['href'], PHP_URL_PATH)); ?></span>
              <span class="ops-home-link__description"><?php echo esc_html($link['description']); ?></span>
              <span class="ops-home-link__meta"><span aria-hidden="true"></span><?php echo esc_html($link['meta']); ?></span>
            </span>
            <span class="ops-home-link__open">Open <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="ops-home__split" aria-label="Ops Home guidance">
      <div class="ops-home-card">
        <div class="ops-home-card__head">
          <h2>What Changed Recently <span><?php echo esc_html(count($changelog)); ?> entries</span></h2>
        </div>
        <div class="ops-home-changelog">
          <?php foreach ($changelog as $entry) : ?>
            <article class="ops-home-change">
              <div class="ops-home-change__date">
                <?php echo esc_html($entry['when']); ?>
                <span><?php echo esc_html($entry['version']); ?></span>
              </div>
              <div class="ops-home-change__body">
                <h3><?php echo esc_html($entry['title']); ?></h3>
                <p><?php echo esc_html($entry['description']); ?></p>
              </div>
              <div class="ops-home-change__tags">
                <?php foreach ($entry['tags'] as $tag) : ?>
                  <span><?php echo esc_html($tag); ?></span>
                <?php endforeach; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="ops-home-card">
        <div class="ops-home-card__head">
          <h2>Where To Go</h2>
          <?php if ($can('resource-hub')) : ?>
            <a href="<?php echo esc_url(home_url('/ops/resource-hub')); ?>">Resource Hub -&gt;</a>
          <?php endif; ?>
        </div>
        <div class="ops-home-guide">
          <?php foreach ($guidance as $item) : ?>
            <div class="ops-home-guide__row">
              <div class="ops-home-guide__role ops-home-guide__role--<?php echo esc_attr($item['class']); ?>">
                <span aria-hidden="true"></span><?php echo esc_html($item['role']); ?>
              </div>
              <p><?php echo esc_html($item['text']); ?></p>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="ops-home-card__foot">Tip: bookmark `/ops/` for the internal front door.</div>
      </div>
    </section>

    <section class="ops-home-notes" aria-labelledby="ops-home-launch-notes">
      <div class="ops-home-notes__head">
        <h2 id="ops-home-launch-notes"><span class="material-symbols-outlined" aria-hidden="true">info</span>Launch Notes</h2>
        <span>Internal</span>
      </div>
      <div class="ops-home-notes__grid">
        <div>
          <span class="material-symbols-outlined" aria-hidden="true">rocket_launch</span>
          <h3>Internal launch workspace</h3>
          <p>Expect minor visual changes as CS, tech, and admin workflows settle into production use.</p>
        </div>
        <div>
          <span class="material-symbols-outlined" aria-hidden="true">lock_person</span>
          <h3>Pages are role-gated</h3>
          <p>If a workspace is missing, ask an admin to review your page access settings.</p>
        </div>
        <div>
          <span class="material-symbols-outlined" aria-hidden="true">menu_book</span>
          <h3>Docs live in Resource Hub</h3>
          <p>Process references, SOPs, and onboarding notes should stay there instead of in chat threads.</p>
        </div>
      </div>
    </section>
  </div>
</div>
