<?php
/**
 * Curated shop-facing updates for OPS Home.
 *
 * Keep this short and practical. CHANGELOG.md remains the technical release
 * history; this file is only for changes that affect daily shop workflow,
 * routing, accountability, or what a user should do differently.
 */
if (!defined('ABSPATH')) exit;

return [
  'reviewed_on' => 'May 14, 2026',
  'source_version' => '0.61.2',
  'updates' => [
    [
      'when' => 'May 14',
      'version' => 'v0.61.2',
      'priority' => 'Must know',
      'title' => 'Cleared blockers can still need schedule review',
      'description' => 'When a blocker was open 4+ hours or the job is due today or past due, clearing it now keeps the job visible for Supervisor schedule review.',
      'action' => 'Supervisors should review the schedule before assuming the job is back on track.',
      'tags' => ['Supervisor', 'Schedule'],
    ],
    [
      'when' => 'May 14',
      'version' => 'v0.61.1',
      'priority' => 'Must know',
      'title' => 'Supervisor actions are focused on Clear Blocker',
      'description' => 'The Supervisor Dashboard now keeps only the Clear Blocker action active, with a required resolution note and audit history.',
      'action' => 'Use Clear Blocker for blocker resolution; leave other action types out of the supervisor flow for now.',
      'tags' => ['Supervisor'],
    ],
    [
      'when' => 'May 14',
      'version' => 'v0.60.0',
      'priority' => 'Helpful',
      'title' => 'Supervisor Dashboard is using live job data',
      'description' => 'Blocked jobs, parts risk, schedule readiness, tech status, QC/rework, ready queue, and attention items now read from Ops jobs.',
      'action' => 'Use the dashboard as a live review surface instead of a prototype reference.',
      'tags' => ['Supervisor', 'Live data'],
    ],
    [
      'when' => 'May 11',
      'version' => 'v0.58.1',
      'priority' => 'Helpful',
      'title' => 'CS Workspace is the primary CS queue surface',
      'description' => 'CS Workspace remains the main place for queue ordering, intake, tech assignment, detail edits, and notes.',
      'action' => 'CS should work from CS Workspace for queue and assignment changes.',
      'tags' => ['CS'],
    ],
  ],
];
