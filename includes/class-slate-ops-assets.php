<?php
if (!defined('ABSPATH')) exit;

class Slate_Ops_Assets {
  public static function logo_img($height = 22) {
    if (!defined('SLATE_OPS_URL')) return '';
    $src = esc_url(SLATE_OPS_URL . 'assets/slate-logo.svg');
    $h = (int)$height;
    return '<img src="' . $src . '" style="height:' . $h . 'px; width:auto; display:block;" alt="Slate">';
  }
}
