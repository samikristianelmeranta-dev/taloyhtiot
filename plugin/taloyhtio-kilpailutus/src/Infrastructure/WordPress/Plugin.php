<?php

declare(strict_types=1);

namespace Kilpailutus\Infrastructure\WordPress;

use Kilpailutus\Application\BidService;
use Kilpailutus\Application\TenderService;
use Kilpailutus\Domain\AccessPolicy;
use Kilpailutus\Domain\SystemClock;

/** Lisäosan käynnistys: koukut ja riippuvuuksien kokoaminen. */
final class Plugin
{
    public static function boot(string $pluginFile): void
    {
        register_activation_hook($pluginFile, [self::class, 'activate']);
        add_action('plugins_loaded', [Schema::class, 'maybeUpgrade']);
        add_action('rest_api_init', static function (): void {
            self::restController()->register();
        });
    }

    public static function activate(): void
    {
        Schema::install();
        Roles::install();
    }

    public static function restController(): RestController
    {
        global $wpdb;
        $clock = new SystemClock();
        $policy = new AccessPolicy();
        $tenders = new WpdbTenderRepository($wpdb);
        $bids = new WpdbBidRepository($wpdb);
        $audit = new WpdbAuditLog($wpdb, $clock);

        /**
         * Tarjousajan vähimmäispituus tunteina julkaisusta.
         *
         * @param int $hours
         */
        $minimumHours = (int) apply_filters('tk_minimum_bid_period_hours', TenderService::DEFAULT_MINIMUM_BID_PERIOD_HOURS);

        return new RestController(
            new TenderService($tenders, $bids, $audit, $clock, $policy, $minimumHours),
            new BidService($tenders, $bids, $audit, $clock, $policy),
        );
    }
}
