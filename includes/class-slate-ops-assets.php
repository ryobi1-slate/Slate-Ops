<?php
if (!defined('ABSPATH')) exit;

class Slate_Ops_Assets {
  public static function logo_img($height = 22) {
    $file = SLATE_OPS_PATH . 'assets/slate-logo.svg';
    if (file_exists($file)) {
      $svg = file_get_contents($file);
      $h   = (int)$height;
      // Inject display style onto the root <svg> element so height is always respected
      $svg = preg_replace('/<svg\b/', '<svg style="height:' . $h . 'px;width:auto;display:block;"', $svg, 1);
      return $svg;
    }
    // Fallback: external URL (requires SLATE_OPS_URL constant)
    if (!defined('SLATE_OPS_URL')) return '';
    $src = esc_url(SLATE_OPS_URL . 'assets/slate-logo.svg');
    $h   = (int)$height;
    return '<img src="' . $src . '" style="height:' . $h . 'px;width:auto;display:block;" alt="Slate">';
  }
}
