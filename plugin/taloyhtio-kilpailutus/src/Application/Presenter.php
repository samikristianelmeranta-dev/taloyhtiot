<?php

declare(strict_types=1);

namespace Kilpailutus\Application;

use Kilpailutus\Domain\Actor;
use Kilpailutus\Domain\Bid;
use Kilpailutus\Domain\RenovationType;
use Kilpailutus\Domain\Tender;

/**
 * Muuntaa oliot rajapinnan JSON-muotoon. Ei päätä oikeuksista: kutsuja
 * antaa vain sen, mitä katsoja saa nähdä.
 */
final class Presenter
{
    /** @return array<string, mixed> */
    public static function tender(Tender $tender, Actor $viewer, \DateTimeImmutable $now, ?int $bidCount = null, ?Bid $ownBid = null): array
    {
        $phase = $tender->phase($now);
        $data = [
            'id' => $tender->id,
            'title' => $tender->title,
            'renovation_type' => $tender->renovationType,
            'renovation_type_label' => RenovationType::ALL[$tender->renovationType] ?? $tender->renovationType,
            'description' => $tender->description,
            'housing_company' => $tender->housingCompany,
            'street_address' => $tender->streetAddress,
            'postal_code' => $tender->postalCode,
            'municipality' => $tender->municipality,
            'desired_start' => Dates::formatDate($tender->desiredStart),
            'desired_end' => Dates::formatDate($tender->desiredEnd),
            'deadline' => Dates::formatDateTime($tender->deadlineAt),
            'status' => $tender->status->value,
            'phase' => $phase->value,
            'phase_label' => $phase->label(),
            'bids_revealed' => $tender->areBidsRevealed($now),
            'published_at' => Dates::formatDateTime($tender->publishedAt),
            'is_owner' => $tender->isOwnedBy($viewer),
        ];
        if ($bidCount !== null) {
            $data['bid_count'] = $bidCount;
        }
        if ($tender->isOwnedBy($viewer) || $viewer->isAdmin()) {
            $data['awarded_bid_id'] = $tender->awardedBidId;
            $data['created_at'] = Dates::formatDateTime($tender->createdAt);
            $data['updated_at'] = Dates::formatDateTime($tender->updatedAt);
        }
        if ($viewer->isContractor()) {
            // Urakoitsija näkee vain oman tarjouksensa tilan, ei muiden tietoja.
            $data['own_bid'] = $ownBid === null ? null : [
                'id' => $ownBid->id,
                'status' => $ownBid->status->value,
                'awarded' => $tender->awardedBidId !== null && $tender->awardedBidId === $ownBid->id,
            ];
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public static function bid(Bid $bid): array
    {
        return [
            'id' => $bid->id,
            'tender_id' => $bid->tenderId,
            'contractor_id' => $bid->contractorId,
            'price_excl_vat_cents' => $bid->priceExclVatCents,
            'price_excl_vat' => self::euros($bid->priceExclVatCents),
            'start_date' => Dates::formatDate($bid->startDate),
            'completion_date' => Dates::formatDate($bid->completionDate),
            'warranty_months' => $bid->warrantyMonths,
            'description' => $bid->description,
            'references' => $bid->referencesText,
            'status' => $bid->status->value,
            'submitted_at' => Dates::formatDateTime($bid->submittedAt),
            'updated_at' => Dates::formatDateTime($bid->updatedAt),
        ];
    }

    /** 1234550 -> "12345.50" */
    public static function euros(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
