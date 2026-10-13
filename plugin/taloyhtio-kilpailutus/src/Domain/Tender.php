<?php

declare(strict_types=1);

namespace Kilpailutus\Domain;

use DateTimeImmutable;
use Kilpailutus\Domain\Exception\Conflict;

/**
 * Kilpailutus eli taloyhtiön tarjouspyyntö.
 *
 * Tilasiirtymät tehdään tämän luokan metodeilla, jotta säännöt ovat yhdessä
 * paikassa ja testattavissa ilman WordPressiä. Kaikki ajat ovat UTC:ssä.
 */
final class Tender
{
    public function __construct(
        public ?int $id,
        public int $ownerId,
        public string $title,
        public string $renovationType,
        public string $description,
        public string $housingCompany,
        public string $streetAddress,
        public string $postalCode,
        public string $municipality,
        public ?DateTimeImmutable $desiredStart,
        public ?DateTimeImmutable $desiredEnd,
        public DateTimeImmutable $deadlineAt,
        public TenderStatus $status,
        public ?int $awardedBidId,
        public ?DateTimeImmutable $publishedAt,
        public ?DateTimeImmutable $cancelledAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public function isOwnedBy(Actor $actor): bool
    {
        return $actor->userId === $this->ownerId;
    }

    public function isOpenForBids(DateTimeImmutable $now): bool
    {
        return $this->status === TenderStatus::Published && $now < $this->deadlineAt;
    }

    /**
     * Suljettujen tarjousten sääntö: tarjoukset avautuvat tilaajalle vasta,
     * kun määräpäivä on mennyt. Ennen määräpäivää peruttu kilpailutus ei avaa
     * tarjouksia koskaan.
     */
    public function areBidsRevealed(DateTimeImmutable $now): bool
    {
        if ($this->publishedAt === null || $now < $this->deadlineAt) {
            return false;
        }
        if ($this->cancelledAt !== null && $this->cancelledAt < $this->deadlineAt) {
            return false;
        }

        return true;
    }

    public function phase(DateTimeImmutable $now): TenderPhase
    {
        return match ($this->status) {
            TenderStatus::Draft => TenderPhase::Draft,
            TenderStatus::Awarded => TenderPhase::Awarded,
            TenderStatus::Cancelled => TenderPhase::Cancelled,
            TenderStatus::Published => $now < $this->deadlineAt ? TenderPhase::Open : TenderPhase::Evaluation,
        };
    }

    public function publish(DateTimeImmutable $now, int $minimumBidPeriodHours): void
    {
        if ($this->status !== TenderStatus::Draft) {
            throw new Conflict('Vain luonnoksen voi julkaista.');
        }
        $earliest = $now->modify(sprintf('+%d hours', $minimumBidPeriodHours));
        if ($this->deadlineAt < $earliest) {
            throw new Conflict(sprintf(
                'Tarjousajan on oltava vähintään %d tuntia julkaisusta. Siirrä määräpäivää myöhemmäksi.',
                $minimumBidPeriodHours,
            ));
        }
        $this->status = TenderStatus::Published;
        $this->publishedAt = $now;
        $this->updatedAt = $now;
    }

    /**
     * Määräpäivää voi vain siirtää myöhemmäksi ja vain tarjousajan ollessa
     * käynnissä. Aikaistaminen tai muuttaminen avaamisen jälkeen mahdollistaisi
     * manipuloinnin.
     */
    public function extendDeadline(DateTimeImmutable $newDeadline, DateTimeImmutable $now): void
    {
        if (!$this->isOpenForBids($now)) {
            throw new Conflict('Määräpäivää voi muuttaa vain tarjousajan ollessa käynnissä.');
        }
        if ($newDeadline <= $this->deadlineAt) {
            throw new Conflict('Uuden määräpäivän on oltava nykyistä myöhäisempi.');
        }
        $this->deadlineAt = $newDeadline;
        $this->updatedAt = $now;
    }

    public function cancel(DateTimeImmutable $now): void
    {
        if ($this->status !== TenderStatus::Draft && $this->status !== TenderStatus::Published) {
            throw new Conflict('Kilpailutusta ei voi enää perua.');
        }
        $this->status = TenderStatus::Cancelled;
        $this->cancelledAt = $now;
        $this->updatedAt = $now;
    }

    public function award(Bid $bid, DateTimeImmutable $now): void
    {
        if ($this->status !== TenderStatus::Published || !$this->areBidsRevealed($now)) {
            throw new Conflict('Urakoitsijan voi valita vasta, kun tarjousaika on päättynyt.');
        }
        if ($bid->tenderId !== $this->id || $bid->status !== BidStatus::Submitted) {
            throw new Conflict('Valittu tarjous ei kuulu tähän kilpailutukseen.');
        }
        $this->status = TenderStatus::Awarded;
        $this->awardedBidId = $bid->id;
        $this->updatedAt = $now;
    }
}
