<?php
/**
 * Plugin Name:       Taloyhtiön remonttikilpailutus
 * Description:       Taloyhtiöiden remonttien kilpailutus: suljetut tarjoukset, jotka avautuvat määräpäivänä.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Taloyhtiöt-projekti
 * Text Domain:       taloyhtio-kilpailutus
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('TK_PLUGIN_VERSION', '0.1.0');
define('TK_PLUGIN_FILE', __FILE__);

// Oma autoloader, jotta asennettava zip ei tarvitse Composeria.
spl_autoload_register(static function (string $class): void {
    $prefix = 'Kilpailutus\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

Kilpailutus\Infrastructure\WordPress\Plugin::boot(__FILE__);
