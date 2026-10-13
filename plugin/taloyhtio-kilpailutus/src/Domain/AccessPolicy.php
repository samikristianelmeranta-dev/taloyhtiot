<?php

declare(strict_types=1);

namespace Kilpailutus\Domain;

/**
 * Kuka näkee mitä. Kaikki käyttöoikeuspäätökset tehdään täällä, ei
 * rajapinnassa tai käyttöliittymässä.
 *
 * Periaatteet:
 *  - Urakoitsija näkee vain oman tarjouksensa.
 *  - Tilaaja näkee tarjoukset vasta määräpäivän jälkeen.
 *  - Ylläpitäjäkään ei näe tarjouksia ennen määräpäivää (puolueettomuus).
 */
final class AccessPolicy
{
    public function canCreateTender(Actor $actor): bool
    {
        return $actor->isHousingCompany();
    }

    public function canViewTender(Actor $actor, Tender $tender): bool
    {
        if ($tender->isOwnedBy($actor) || $actor->isAdmin()) {
            return true;
        }

        return $actor->isContractor() && $tender->status !== TenderStatus::Draft;
    }

    public function canManageTender(Actor $actor, Tender $tender): bool
    {
        return $tender->isOwnedBy($actor);
    }

    public function canSubmitBid(Actor $actor, Tender $tender, \DateTimeImmutable $now): bool
    {
        return $actor->isContractor()
            && !$tender->isOwnedBy($actor)
            && $tender->isOpenForBids($now);
    }

    public function canViewBid(Actor $actor, Tender $tender, Bid $bid, \DateTimeImmutable $now): bool
    {
        if ($bid->isOwnedBy($actor)) {
            return true;
        }

        return $this->canViewAllBids($actor, $tender, $now) && $bid->status === BidStatus::Submitted;
    }

    public function canViewAllBids(Actor $actor, Tender $tender, \DateTimeImmutable $now): bool
    {
        if (!$tender->areBidsRevealed($now)) {
            return false;
        }

        return $tender->isOwnedBy($actor) || $actor->isAdmin();
    }

    /** Tilaaja ja ylläpitäjä näkevät jätettyjen tarjousten määrän, eivät sisältöä. */
    public function canViewBidCount(Actor $actor, Tender $tender): bool
    {
        return $tender->isOwnedBy($actor) || $actor->isAdmin();
    }
}
