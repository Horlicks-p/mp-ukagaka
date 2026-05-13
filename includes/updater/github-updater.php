<?php
/**
 * GitHub Auto-Updater
 *
 * Plugin Update Checker v5.6 を利用して GitHub Release からの自動更新を提供する。
 * Release asset（mp-ukagaka.zip）が存在する場合はそれを優先してダウンロードする。
 *
 * library が存在しない場合は更新チェックをスキップし、fatal にしない。
 *
 * @package MP_Ukagaka
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit();
}

$puc_bootstrap = MPU_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
if ( ! file_exists( $puc_bootstrap ) ) {
    return;
}

require_once $puc_bootstrap;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$mpu_updater = PucFactory::buildUpdateChecker(
    'https://github.com/Horlicks-p/mp-ukagaka/',
    MPU_PLUGIN_DIR . 'mp-ukagaka.php',
    'mp-ukagaka'
);

// Release asset（mp-ukagaka.zip）を優先ダウンロードする
$mpu_updater->getVcsApi()->enableReleaseAssets();
