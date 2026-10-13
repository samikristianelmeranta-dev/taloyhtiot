<?php

declare(strict_types=1);

namespace Kilpailutus\Infrastructure\WordPress;

/**
 * Omat tietokantataulut. Kilpailutuksia ja tarjouksia ei tallenneta
 * wp_posts/wp_postmeta-tauluihin, jotta tiedot ovat selkeässä muodossa ja
 * siirrettävissä myöhemmin omaan sovellukseen.
 */
final class Schema
{
    public const VERSION = '1';
    public const OPTION = 'tk_db_version';

    public static function table(string $name): string
    {
        global $wpdb;

        return $wpdb->prefix . 'tk_' . $name;
    }

    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $tenders = self::table('tenders');
        $bids = self::table('bids');
        $audit = self::table('audit_log');

        // dbDelta vaatii tämän muotoilun: kenttä per rivi, kaksi välilyöntiä PRIMARY KEY:n jälkeen.
        dbDelta("CREATE TABLE {$tenders} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  owner_id bigint(20) unsigned NOT NULL,
  title varchar(200) NOT NULL,
  renovation_type varchar(40) NOT NULL,
  description longtext NOT NULL,
  housing_company varchar(200) NOT NULL,
  street_address varchar(200) NOT NULL,
  postal_code varchar(10) NOT NULL,
  municipality varchar(100) NOT NULL,
  desired_start date NULL,
  desired_end date NULL,
  deadline_at datetime NOT NULL,
  status varchar(20) NOT NULL,
  awarded_bid_id bigint(20) unsigned NULL,
  published_at datetime NULL,
  cancelled_at datetime NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY owner_id (owner_id),
  KEY status_deadline (status,deadline_at)
) {$charset};");

        dbDelta("CREATE TABLE {$bids} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  tender_id bigint(20) unsigned NOT NULL,
  contractor_id bigint(20) unsigned NOT NULL,
  price_excl_vat_cents bigint(20) unsigned NOT NULL,
  start_date date NULL,
  completion_date date NULL,
  warranty_months smallint(5) unsigned NOT NULL DEFAULT 0,
  description longtext NOT NULL,
  references_text longtext NOT NULL,
  status varchar(20) NOT NULL,
  submitted_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY tender_contractor (tender_id,contractor_id),
  KEY contractor_id (contractor_id)
) {$charset};");

        dbDelta("CREATE TABLE {$audit} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  event varchar(50) NOT NULL,
  actor_id bigint(20) unsigned NOT NULL,
  tender_id bigint(20) unsigned NULL,
  bid_id bigint(20) unsigned NULL,
  context text NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY tender_id (tender_id),
  KEY actor_id (actor_id)
) {$charset};");

        update_option(self::OPTION, self::VERSION);
    }

    public static function maybeUpgrade(): void
    {
        if (get_option(self::OPTION) !== self::VERSION) {
            self::install();
        }
    }
}
