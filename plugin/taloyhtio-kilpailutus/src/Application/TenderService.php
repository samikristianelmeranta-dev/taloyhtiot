<?php

declare(strict_types=1);

namespace Kilpailutus\Application;

use Kilpailutus\Domain\AccessPolicy;
use Kilpailutus\Domain\Actor;
use Kilpailutus\Domain\Bid;
use Kilpailutus\Domain\Clock;
use Kilpailutus\Domain\Exception\Conflict;
use Kilpailutus\Domain\Exception\Forbidden;
use Kilpailutus\Domain\Exception\NotFound;
use Kilpailutus\Domain\Exception\ValidationError;
use Kilpailutus\Domain\Repository\AuditLog;
use Kilpailutus\Domain\Repository\BidRepository;
use Kilpailutus\Domain\Repository\TenderRepository;
use Kilpailutus\Domain\Tender;
use Kilpailutus\Domain\TenderStatus;

/** Kilpailutusten käyttötapaukset: luonti, muokkaus, julkaisu, peruminen ja valinta. */
final class TenderService
{
    public const DEFAULT_MINIMUM_BID_PERIOD_HOURS = 72;

    public function __construct(
        private readonly TenderRepository $tenders,
        private readonly BidRepository $bids,
        private readonly AuditLog $audit,
        private readonly Clock $clock,
        private readonly AccessPolicy $policy = new AccessPolicy(),
        private readonly int $minimumBidPeriodHours = self::DEFAULT_MINIMUM_BID_PERIOD_HOURS,
    ) {
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function create(Actor $actor, array $data): array
    {
        if (!$this->policy->canCreateTender($actor)) {
            throw new Forbidden('Vain taloyhtiön tai isännöitsijän tunnuksella voi luoda kilpailutuksen.');
        }
        $fields = TenderInput::validate($data, false);
        $now = $this->clock->now();

        $tender = new Tender(
            id: null,
            ownerId: $actor->userId,
            title: $fields['title'],
            renovationType: $fields['renovationType'],
            description: $fields['description'],
            housingCompany: $fields['housingCompany'],
            streetAddress: $fields['streetAddress'],
            postalCode: $fields['postalCode'],
            municipality: $fields['municipality'],
            desiredStart: $fields['desiredStart'],
            desiredEnd: $fields['desiredEnd'],
            deadlineAt: $fields['deadlineAt'],
            status: TenderStatus::Draft,
            awardedBidId: null,
            publishedAt: null,
            cancelledAt: null,
            createdAt: $now,
            updatedAt: $now,
        );
        $tender = $this->tenders->save($tender);
        $this->audit->record('tender_created', $actor->userId, $tender->id);

        return $this->present($actor, $tender);
    }

    /** Luonnosta voi muokata vapaasti; julkaistua ei, jotta tarjoajat saavat saman tiedon. */
    public function update(Actor $actor, int $id, array $data): array
    {
        $tender = $this->managedTender($actor, $id);
        if ($tender->status !== TenderStatus::Draft) {
            throw new Conflict('Julkaistua kilpailutusta ei voi muokata. Voit siirtää määräpäivää tai perua kilpailutuksen.');
        }
        $fields = TenderInput::validate($data, true);
        $start = array_key_exists('desiredStart', $fields) ? $fields['desiredStart'] : $tender->desiredStart;
        $end = array_key_exists('desiredEnd', $fields) ? $fields['desiredEnd'] : $tender->desiredEnd;
        if ($start && $end && $end < $start) {
            throw new ValidationError(['desired_end' => 'Valmistumisen on oltava aloituksen jälkeen.']);
        }
        TenderInput::apply($tender, $fields);
        $tender->updatedAt = $this->clock->now();
        $this->tenders->save($tender);

        return $this->present($actor, $tender);
    }

    public function publish(Actor $actor, int $id): array
    {
        $tender = $this->managedTender($actor, $id);
        $tender->publish($this->clock->now(), $this->minimumBidPeriodHours);
        $this->tenders->save($tender);
        $this->audit->record('tender_published', $actor->userId, $tender->id);

        return $this->present($actor, $tender);
    }

    public function extendDeadline(Actor $actor, int $id, array $data): array
    {
        $tender = $this->managedTender($actor, $id);
        $in = new Input($data);
        $deadline = $in->dateTime('deadline', true);
        if ($in->errors() !== []) {
            throw new ValidationError($in->errors());
        }
        $previous = $tender->deadlineAt;
        $tender->extendDeadline($deadline, $this->clock->now());
        $this->tenders->save($tender);
        $this->audit->record('deadline_extended', $actor->userId, $tender->id, null, [
            'from' => Dates::formatDateTime($previous),
            'to' => Dates::formatDateTime($deadline),
        ]);

        return $this->present($actor, $tender);
    }

    public function cancel(Actor $actor, int $id): array
    {
        $tender = $this->managedTender($actor, $id);
        $tender->cancel($this->clock->now());
        $this->tenders->save($tender);
        $this->audit->record('tender_cancelled', $actor->userId, $tender->id);

        return $this->present($actor, $tender);
    }

    public function award(Actor $actor, int $id, int $bidId): array
    {
        $tender = $this->managedTender($actor, $id);
        $bid = $this->bids->find($bidId);
        if ($bid === null || $bid->tenderId !== $tender->id) {
            throw new NotFound('Tarjousta ei löydy.');
        }
        $tender->award($bid, $this->clock->now());
        $this->tenders->save($tender);
        $this->audit->record('tender_awarded', $actor->userId, $tender->id, $bid->id);

        return $this->present($actor, $tender);
    }

    /** @return array<string, mixed> */
    public function get(Actor $actor, int $id): array
    {
        return $this->present($actor, $this->visibleTender($actor, $id));
    }

    /**
     * Taloyhtiö: omat kilpailutukset. Urakoitsija: avoimet sekä ne, joihin on
     * jättänyt tarjouksen. Ylläpitäjä: kaikki.
     *
     * @return list<array<string, mixed>>
     */
    public function listFor(Actor $actor): array
    {
        $now = $this->clock->now();
        $byId = [];
        $ownBids = [];

        if ($actor->isAdmin()) {
            foreach ($this->tenders->findAll() as $t) {
                $byId[$t->id] = $t;
            }
        }
        if ($actor->isHousingCompany()) {
            foreach ($this->tenders->findByOwner($actor->userId) as $t) {
                $byId[$t->id] = $t;
            }
        }
        if ($actor->isContractor()) {
            foreach ($this->bids->findByContractor($actor->userId) as $bid) {
                $ownBids[$bid->tenderId] = $bid;
            }
            $tenders = array_merge($this->tenders->findOpen($now), $this->tenders->findByIds(array_keys($ownBids)));
            foreach ($tenders as $t) {
                if ($this->policy->canViewTender($actor, $t)) {
                    $byId[$t->id] = $t;
                }
            }
        }

        usort($byId, static fn (Tender $a, Tender $b) => $a->deadlineAt <=> $b->deadlineAt);

        return array_map(fn (Tender $t) => $this->present($actor, $t, $ownBids[$t->id] ?? null, true), $byId);
    }

    /** Hakee kilpailutuksen, jota toimija saa hallinnoida. */
    private function managedTender(Actor $actor, int $id): Tender
    {
        $tender = $this->visibleTender($actor, $id);
        if (!$this->policy->canManageTender($actor, $tender)) {
            throw new Forbidden('Vain kilpailutuksen tekijä voi tehdä tämän.');
        }

        return $tender;
    }

    /** Näkymätön kilpailutus näyttää samalta kuin olematon, ettei tunnisteita voi arvailla. */
    private function visibleTender(Actor $actor, int $id): Tender
    {
        $tender = $this->tenders->find($id);
        if ($tender === null || !$this->policy->canViewTender($actor, $tender)) {
            throw new NotFound('Kilpailutusta ei löydy.');
        }

        return $tender;
    }

    /** @return array<string, mixed> */
    private function present(Actor $actor, Tender $tender, ?Bid $ownBid = null, bool $ownBidLoaded = false): array
    {
        $count = $this->policy->canViewBidCount($actor, $tender)
            ? $this->bids->countSubmittedByTender((int) $tender->id)
            : null;
        if (!$ownBidLoaded && $actor->isContractor()) {
            $ownBid = $this->bids->findByTenderAndContractor((int) $tender->id, $actor->userId);
        }

        return Presenter::tender($tender, $actor, $this->clock->now(), $count, $ownBid);
    }
}
