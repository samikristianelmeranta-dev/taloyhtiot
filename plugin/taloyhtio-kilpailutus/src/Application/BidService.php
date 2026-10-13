<?php

declare(strict_types=1);

namespace Kilpailutus\Application;

use Kilpailutus\Domain\AccessPolicy;
use Kilpailutus\Domain\Actor;
use Kilpailutus\Domain\Bid;
use Kilpailutus\Domain\BidStatus;
use Kilpailutus\Domain\Clock;
use Kilpailutus\Domain\Exception\Conflict;
use Kilpailutus\Domain\Exception\Forbidden;
use Kilpailutus\Domain\Exception\NotFound;
use Kilpailutus\Domain\Repository\AuditLog;
use Kilpailutus\Domain\Repository\BidRepository;
use Kilpailutus\Domain\Repository\TenderRepository;
use Kilpailutus\Domain\Tender;

/** Tarjousten käyttötapaukset: jättäminen, muokkaus, peruminen ja avaaminen. */
final class BidService
{
    public function __construct(
        private readonly TenderRepository $tenders,
        private readonly BidRepository $bids,
        private readonly AuditLog $audit,
        private readonly Clock $clock,
        private readonly AccessPolicy $policy = new AccessPolicy(),
    ) {
    }

    /**
     * Jättää tai päivittää urakoitsijan oman tarjouksen. Yhdellä urakoitsijalla
     * on yksi tarjous per kilpailutus, ja sitä voi muokata määräpäivään asti.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function submit(Actor $actor, int $tenderId, array $data): array
    {
        $tender = $this->visibleTender($actor, $tenderId);
        $now = $this->clock->now();
        if (!$this->policy->canSubmitBid($actor, $tender, $now)) {
            throw $actor->isContractor()
                ? new Conflict('Tarjousaika ei ole käynnissä.')
                : new Forbidden('Vain urakoitsija voi jättää tarjouksen.');
        }
        $fields = BidInput::validate($data);

        $bid = $this->bids->findByTenderAndContractor($tenderId, $actor->userId);
        $event = 'bid_updated';
        if ($bid === null) {
            $event = 'bid_submitted';
            $bid = new Bid(
                id: null,
                tenderId: $tenderId,
                contractorId: $actor->userId,
                priceExclVatCents: $fields['priceExclVatCents'],
                startDate: $fields['startDate'],
                completionDate: $fields['completionDate'],
                warrantyMonths: $fields['warrantyMonths'],
                description: $fields['description'],
                referencesText: $fields['referencesText'],
                status: BidStatus::Submitted,
                submittedAt: $now,
                updatedAt: $now,
            );
        } else {
            if ($bid->status === BidStatus::Withdrawn) {
                $event = 'bid_submitted';
                $bid->submittedAt = $now;
            }
            foreach ($fields as $prop => $value) {
                $bid->{$prop} = $value;
            }
            $bid->status = BidStatus::Submitted;
            $bid->updatedAt = $now;
        }
        $bid = $this->bids->save($bid);
        $this->audit->record($event, $actor->userId, $tenderId, $bid->id);

        return Presenter::bid($bid);
    }

    /** @return array<string, mixed> */
    public function withdraw(Actor $actor, int $tenderId): array
    {
        $tender = $this->visibleTender($actor, $tenderId);
        $bid = $this->ownBidOrFail($actor, $tenderId);
        if (!$tender->isOpenForBids($this->clock->now())) {
            throw new Conflict('Tarjousta ei voi perua tarjousajan päätyttyä.');
        }
        if ($bid->status === BidStatus::Withdrawn) {
            return Presenter::bid($bid);
        }
        $bid->status = BidStatus::Withdrawn;
        $bid->updatedAt = $this->clock->now();
        $this->bids->save($bid);
        $this->audit->record('bid_withdrawn', $actor->userId, $tenderId, $bid->id);

        return Presenter::bid($bid);
    }

    /** @return array<string, mixed> */
    public function own(Actor $actor, int $tenderId): array
    {
        $this->visibleTender($actor, $tenderId);

        return Presenter::bid($this->ownBidOrFail($actor, $tenderId));
    }

    /**
     * Kilpailutuksen kaikki tarjoukset tilaajalle (tai ylläpitäjälle)
     * määräpäivän jälkeen. Jokainen avaus kirjataan lokiin.
     *
     * @return list<array<string, mixed>>
     */
    public function listForTender(Actor $actor, int $tenderId): array
    {
        $tender = $this->visibleTender($actor, $tenderId);
        $now = $this->clock->now();
        if (!$this->policy->canViewAllBids($actor, $tender, $now)) {
            if ($tender->isOwnedBy($actor) || $actor->isAdmin()) {
                throw new Forbidden('Tarjoukset avautuvat vasta tarjousten määräpäivänä.');
            }
            throw new Forbidden('Et voi nähdä muiden tarjouksia.');
        }
        $bids = $this->bids->findSubmittedByTender($tenderId);
        $this->audit->record('bids_viewed', $actor->userId, $tenderId, null, ['count' => count($bids)]);
        usort($bids, static fn (Bid $a, Bid $b) => $a->priceExclVatCents <=> $b->priceExclVatCents);

        return array_map([Presenter::class, 'bid'], $bids);
    }

    private function ownBidOrFail(Actor $actor, int $tenderId): Bid
    {
        $bid = $this->bids->findByTenderAndContractor($tenderId, $actor->userId);
        if ($bid === null) {
            throw new NotFound('Et ole jättänyt tarjousta tähän kilpailutukseen.');
        }

        return $bid;
    }

    private function visibleTender(Actor $actor, int $tenderId): Tender
    {
        $tender = $this->tenders->find($tenderId);
        if ($tender === null || !$this->policy->canViewTender($actor, $tender)) {
            throw new NotFound('Kilpailutusta ei löydy.');
        }

        return $tender;
    }
}
