<?php
/**
 * Savutesti oikeaa WordPress-asennusta vasten: ajaa koko kilpailutuksen
 * REST-rajapinnan kautta ja tarkistaa käyttöoikeudet sekä tietokantataulut.
 *
 * Käyttö (lisäosa aktivoituna, TESTIASENNUKSESSA, ei tuotannossa):
 *   php bin/wp-smoke-test.php /polku/wordpressiin
 *
 * Luo testikäyttäjiä ja -kilpailutuksia, ja poistaa ne lopuksi.
 */

declare(strict_types=1);

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require rtrim($argv[1] ?? '', '/') . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$failures = 0;
$check = static function (string $label, bool $ok, mixed $debug = null) use (&$failures): void {
    echo ($ok ? '  ok   ' : '  FAIL ') . $label . ($ok || $debug === null ? '' : ' → ' . json_encode($debug, JSON_UNESCAPED_UNICODE)) . "\n";
    $failures += $ok ? 0 : 1;
};

$call = static function (?int $userId, string $method, string $path, array $body = []): WP_REST_Response {
    wp_set_current_user($userId ?? 0);
    $request = new WP_REST_Request($method, '/kilpailutus/v1' . $path);
    if ($body !== []) {
        $request->set_header('Content-Type', 'application/json');
        $request->set_body((string) wp_json_encode($body));
    }

    return rest_get_server()->dispatch($request);
};

$suffix = wp_generate_password(6, false);
$user = static fn (string $login, string $role) => wp_insert_user([
    'user_login' => "{$login}_{$suffix}",
    'user_pass' => wp_generate_password(),
    'user_email' => "{$login}_{$suffix}@example.invalid",
    'display_name' => ucfirst($login),
    'role' => $role,
]);
$board = $user('hallitus', 'tk_taloyhtio');
$other = $user('toinenhallitus', 'tk_taloyhtio');
$contrA = $user('urakoitsijaa', 'tk_urakoitsija');
$contrB = $user('urakoitsijab', 'tk_urakoitsija');
$admin = $user('yllapitaja', 'administrator');
global $wpdb;

echo "Kilpailutuksen luonti ja julkaisu\n";
$r = $call(null, 'GET', '/tenders');
$check('kirjautumaton saa 401', $r->get_status() === 401, $r->get_data());
$r = $call($contrA, 'POST', '/tenders', ['title' => 'x']);
$check('urakoitsija ei voi luoda kilpailutusta (403)', $r->get_status() === 403, $r->get_data());
$r = $call($board, 'POST', '/tenders', ['title' => '', 'postal_code' => '1']);
$check('validointivirhe 422 kenttäkohtaisesti', $r->get_status() === 422 && isset($r->get_data()['data']['fields']['postal_code']), $r->get_data());

$deadline = (new DateTimeImmutable('+10 days', new DateTimeZone('Europe/Helsinki')))->format('Y-m-d\TH:i');
$r = $call($board, 'POST', '/tenders', [
    'title' => 'Ikkunaremontti',
    'renovation_type' => 'ikkunat',
    'description' => 'Testikohde',
    'housing_company' => 'As Oy Testi',
    'street_address' => 'Testikatu 1',
    'postal_code' => '00100',
    'municipality' => 'Helsinki',
    'desired_start' => '2027-05-01',
    'deadline' => $deadline,
]);
$check('taloyhtiö luo luonnoksen (201)', $r->get_status() === 201, $r->get_data());
$id = (int) ($r->get_data()['id'] ?? 0);

$r = $call($contrA, 'GET', "/tenders/{$id}");
$check('luonnos ei näy urakoitsijalle (404)', $r->get_status() === 404, $r->get_data());
$r = $call($board, 'PATCH', "/tenders/{$id}", ['desired_start' => '']);
$check('luonnosta voi muokata', $r->get_status() === 200 && $r->get_data()['desired_start'] === null, $r->get_data());
$r = $call($board, 'POST', "/tenders/{$id}/publish");
$check('julkaisu', $r->get_status() === 200 && $r->get_data()['phase'] === 'open', $r->get_data());
$r = $call($other, 'GET', "/tenders/{$id}");
$check('toinen taloyhtiö ei näe kilpailutusta (404)', $r->get_status() === 404, $r->get_data());

echo "Tarjoukset\n";
$r = $call($contrA, 'PUT', "/tenders/{$id}/bids/mine", ['price_excl_vat' => '185000,00', 'description' => 'A', 'warranty_months' => 24]);
$check('urakoitsija A jättää tarjouksen', $r->get_status() === 200 && $r->get_data()['price_excl_vat_cents'] === 18500000, $r->get_data());
$r = $call($contrB, 'PUT', "/tenders/{$id}/bids/mine", ['price_excl_vat' => '170000', 'description' => 'B']);
$check('urakoitsija B jättää tarjouksen', $r->get_status() === 200, $r->get_data());
$bidB = (int) ($r->get_data()['id'] ?? 0);
$r = $call($contrB, 'PUT', "/tenders/{$id}/bids/mine", ['price_excl_vat' => '169000', 'description' => 'B2']);
$check('urakoitsija B päivittää saman tarjouksen', $r->get_data()['id'] === $bidB, $r->get_data());
$r = $call($contrA, 'GET', "/tenders/{$id}/bids/mine");
$check('A näkee oman tarjouksensa', $r->get_status() === 200 && $r->get_data()['description'] === 'A', $r->get_data());

foreach (['tilaaja' => $board, 'ylläpitäjä' => $admin, 'urakoitsija' => $contrA] as $who => $uid) {
    $r = $call($uid, 'GET', "/tenders/{$id}/bids");
    $check("{$who} ei näe tarjouksia ennen määräpäivää (403)", $r->get_status() === 403, $r->get_data());
}
$r = $call($board, 'GET', "/tenders/{$id}");
$check('tilaaja näkee tarjousten määrän', ($r->get_data()['bid_count'] ?? null) === 2, $r->get_data());
$r = $call($contrA, 'GET', "/tenders/{$id}");
$check('urakoitsija ei näe tarjousten määrää', !array_key_exists('bid_count', $r->get_data()), $r->get_data());
$r = $call($board, 'POST', "/tenders/{$id}/award", ['bid_id' => $bidB]);
$check('valinta ei onnistu ennen määräpäivää (409)', $r->get_status() === 409, $r->get_data());

echo "Määräpäivän jälkeen\n";
// Simuloidaan ajan kulumista siirtämällä määräpäivä menneisyyteen.
$wpdb->update($wpdb->prefix . 'tk_tenders', ['deadline_at' => gmdate('Y-m-d H:i:s', time() - 60)], ['id' => $id]);
$r = $call($contrA, 'PUT', "/tenders/{$id}/bids/mine", ['price_excl_vat' => '1', 'description' => 'myöhässä']);
$check('tarjousta ei voi muuttaa määräpäivän jälkeen (409)', $r->get_status() === 409, $r->get_data());
$r = $call($board, 'GET', "/tenders/{$id}/bids");
$bids = $r->get_data();
$check('tilaaja näkee tarjoukset hintajärjestyksessä', $r->get_status() === 200 && array_column($bids, 'price_excl_vat_cents') === [16900000, 18500000], $bids);
$check('tarjouksissa urakoitsijan nimi', ($bids[0]['contractor_name'] ?? '') === 'Urakoitsijab', $bids);
$r = $call($contrA, 'GET', "/tenders/{$id}/bids");
$check('urakoitsija ei näe muiden tarjouksia määräpäivänkään jälkeen (403)', $r->get_status() === 403, $r->get_data());
$r = $call($board, 'POST', "/tenders/{$id}/award", ['bid_id' => $bidB]);
$check('urakoitsijan valinta', $r->get_status() === 200 && $r->get_data()['phase'] === 'awarded', $r->get_data());
$r = $call($contrB, 'GET', "/tenders/{$id}");
$check('voittaja näkee voittaneensa', ($r->get_data()['own_bid']['awarded'] ?? null) === true, $r->get_data());
$r = $call($contrA, 'GET', "/tenders/{$id}");
$check('hävinnyt ei näe voittajan tunnistetta', ($r->get_data()['own_bid']['awarded'] ?? null) === false && !array_key_exists('awarded_bid_id', $r->get_data()), $r->get_data());

$events = $wpdb->get_col($wpdb->prepare("SELECT event FROM {$wpdb->prefix}tk_audit_log WHERE tender_id = %d ORDER BY id", $id));
$check('tapahtumaloki kirjattu', in_array('bids_viewed', $events, true) && in_array('tender_awarded', $events, true), $events);

// Siivous
$wpdb->delete($wpdb->prefix . 'tk_bids', ['tender_id' => $id]);
$wpdb->delete($wpdb->prefix . 'tk_audit_log', ['tender_id' => $id]);
$wpdb->delete($wpdb->prefix . 'tk_tenders', ['id' => $id]);
foreach ([$board, $other, $contrA, $contrB, $admin] as $uid) {
    wp_delete_user($uid);
}

echo $failures === 0 ? "\nKaikki tarkistukset ok.\n" : "\n{$failures} tarkistusta epäonnistui.\n";
exit($failures === 0 ? 0 : 1);
