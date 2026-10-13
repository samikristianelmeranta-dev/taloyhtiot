<?php

declare(strict_types=1);

namespace Kilpailutus\Infrastructure\WordPress;

use Kilpailutus\Application\BidService;
use Kilpailutus\Application\TenderService;
use Kilpailutus\Domain\Actor;
use Kilpailutus\Domain\Exception\DomainError;
use Kilpailutus\Domain\RenovationType;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST-rajapinta /wp-json/kilpailutus/v1/. Ohut kerros: lukee pyynnön,
 * kutsuu palvelua ja muuntaa virheet HTTP-vastauksiksi. Kaikki
 * oikeustarkistukset ovat palveluissa (AccessPolicy).
 */
final class RestController
{
    public const NAMESPACE = 'kilpailutus/v1';

    public function __construct(
        private readonly TenderService $tenders,
        private readonly BidService $bids,
    ) {
    }

    public function register(): void
    {
        $id = '(?P<id>\d+)';
        $routes = [
            ['/renovation-types', WP_REST_Server::READABLE, fn () => $this->renovationTypes()],
            ['/tenders', WP_REST_Server::READABLE, fn (Actor $a) => $this->tenders->listFor($a)],
            ['/tenders', WP_REST_Server::CREATABLE, fn (Actor $a, WP_REST_Request $r) => new WP_REST_Response($this->tenders->create($a, $this->body($r)), 201)],
            ["/tenders/{$id}", WP_REST_Server::READABLE, fn (Actor $a, WP_REST_Request $r) => $this->tenders->get($a, (int) $r['id'])],
            ["/tenders/{$id}", 'PATCH', fn (Actor $a, WP_REST_Request $r) => $this->tenders->update($a, (int) $r['id'], $this->body($r))],
            ["/tenders/{$id}/publish", WP_REST_Server::CREATABLE, fn (Actor $a, WP_REST_Request $r) => $this->tenders->publish($a, (int) $r['id'])],
            ["/tenders/{$id}/deadline", WP_REST_Server::CREATABLE, fn (Actor $a, WP_REST_Request $r) => $this->tenders->extendDeadline($a, (int) $r['id'], $this->body($r))],
            ["/tenders/{$id}/cancel", WP_REST_Server::CREATABLE, fn (Actor $a, WP_REST_Request $r) => $this->tenders->cancel($a, (int) $r['id'])],
            ["/tenders/{$id}/award", WP_REST_Server::CREATABLE, fn (Actor $a, WP_REST_Request $r) => $this->tenders->award($a, (int) $r['id'], (int) ($this->body($r)['bid_id'] ?? 0))],
            ["/tenders/{$id}/bids", WP_REST_Server::READABLE, fn (Actor $a, WP_REST_Request $r) => $this->withContractorNames($this->bids->listForTender($a, (int) $r['id']))],
            ["/tenders/{$id}/bids/mine", WP_REST_Server::READABLE, fn (Actor $a, WP_REST_Request $r) => $this->bids->own($a, (int) $r['id'])],
            ["/tenders/{$id}/bids/mine", 'PUT', fn (Actor $a, WP_REST_Request $r) => $this->bids->submit($a, (int) $r['id'], $this->body($r))],
            ["/tenders/{$id}/bids/mine", WP_REST_Server::DELETABLE, fn (Actor $a, WP_REST_Request $r) => $this->bids->withdraw($a, (int) $r['id'])],
        ];

        foreach ($routes as [$path, $methods, $handler]) {
            register_rest_route(self::NAMESPACE, $path, [
                'methods' => $methods,
                'callback' => fn (WP_REST_Request $request) => $this->handle($handler, $request),
                // Vain kirjautuneille. Tarkemmat oikeudet ratkaisee AccessPolicy.
                'permission_callback' => static fn () => is_user_logged_in(),
            ]);
        }
    }

    private function handle(callable $handler, WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $actor = Roles::currentActor();
        if ($actor === null) {
            return new WP_Error('tk_unauthorized', 'Kirjaudu sisään.', ['status' => 401]);
        }
        try {
            $result = $handler($actor, $request);

            return $result instanceof WP_REST_Response ? $result : new WP_REST_Response($result, 200);
        } catch (DomainError $e) {
            return new WP_Error($e->errorCode(), $e->getMessage(), ['status' => $e->httpStatus()] + $e->details());
        }
    }

    /** @return array<string, mixed> */
    private function body(WP_REST_Request $request): array
    {
        $json = $request->get_json_params();

        return is_array($json) ? $json : $request->get_body_params();
    }

    /** @return list<array{slug:string,label:string}> */
    private function renovationTypes(): array
    {
        $out = [];
        foreach (RenovationType::ALL as $slug => $label) {
            $out[] = ['slug' => $slug, 'label' => $label];
        }

        return $out;
    }

    /**
     * Lisää urakoitsijan nimen tarjouksiin vertailua varten. Tämä on ainoa
     * kohta, jossa tarvitaan WordPressin käyttäjätietoja.
     *
     * @param list<array<string, mixed>> $bids
     * @return list<array<string, mixed>>
     */
    private function withContractorNames(array $bids): array
    {
        foreach ($bids as &$bid) {
            $user = get_userdata((int) $bid['contractor_id']);
            $bid['contractor_name'] = $user ? $user->display_name : '';
        }

        return $bids;
    }
}
