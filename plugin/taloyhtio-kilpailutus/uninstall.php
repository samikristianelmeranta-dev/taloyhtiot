<?php
/**
 * Lisäosan poisto. Poistaa roolit mutta EI tietokantatauluja: kilpailutukset
 * ja tarjoukset ovat taloyhtiöiden tietoja, joita ei saa hävittää vahingossa.
 * Taulut (wp_tk_tenders, wp_tk_bids, wp_tk_audit_log) poistetaan käsin.
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

remove_role('tk_taloyhtio');
remove_role('tk_urakoitsija');
get_role('administrator')?->remove_cap('tk_manage');
