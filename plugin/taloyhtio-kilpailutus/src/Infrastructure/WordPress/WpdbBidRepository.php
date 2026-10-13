<?php

declare(strict_types=1);

namespace Kilpailutus\Infrastructure\WordPress;

use Kilpailutus\Domain\Bid;
use Kilpailutus\Domain\BidStatus;
use Kilpailutus\Domain\Repository\BidRepository;

final class WpdbBidRepository implements BidRepository
{
    public function __construct(private readonly \wpdb $db)
    {
    }

    public function find(int $id): ?Bid
    {
        return $this->one('WHERE id = %d', [$id]);
    }

    public function save(Bid $bid): Bid
    {
        $data = [
            'tender_id' => $bid->tenderId,
            'contractor_id' => $bid->contractorId,
            'price_excl_vat_cents' => $bid->priceExclVatCents,
            'start_date' => Rows::fromDate($bid->startDate),
            'completion_date' => Rows::fromDate($bid->completionDate),
            'warranty_months' => $bid->warrantyMonths,
            'description' => $bid->description,
            'references_text' => $bid->referencesText,
            'status' => $bid->status->value,
            'submitted_at' => Rows::fromDateTime($bid->submittedAt),
            'updated_at' => Rows::fromDateTime($bid->updatedAt),
        ];
        $formats = ['%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s'];

        if ($bid->id === null) {
            if ($this->db->insert(Schema::table('bids'), $data, $formats) === false) {
                throw new \RuntimeException('Tarjouksen tallennus epäonnistui: ' . $this->db->last_error);
            }
            $bid->id = (int) $this->db->insert_id;
        } elseif ($this->db->update(Schema::table('bids'), $data, ['id' => $bid->id], $formats, ['%d']) === false) {
            throw new \RuntimeException('Tarjouksen tallennus epäonnistui: ' . $this->db->last_error);
        }

        return $bid;
    }

    public function findByTenderAndContractor(int $tenderId, int $contractorId): ?Bid
    {
        return $this->one('WHERE tender_id = %d AND contractor_id = %d', [$tenderId, $contractorId]);
    }

    public function findSubmittedByTender(int $tenderId): array
    {
        return $this->many('WHERE tender_id = %d AND status = %s', [$tenderId, BidStatus::Submitted->value]);
    }

    public function countSubmittedByTender(int $tenderId): int
    {
        return (int) $this->db->get_var($this->db->prepare(
            'SELECT COUNT(*) FROM ' . Schema::table('bids') . ' WHERE tender_id = %d AND status = %s',
            $tenderId,
            BidStatus::Submitted->value,
        ));
    }

    public function findByContractor(int $contractorId): array
    {
        return $this->many('WHERE contractor_id = %d', [$contractorId]);
    }

    /** @param list<scalar> $args */
    private function one(string $where, array $args): ?Bid
    {
        return $this->many($where, $args)[0] ?? null;
    }

    /** @param list<scalar> $args @return list<Bid> */
    private function many(string $where, array $args): array
    {
        $sql = $this->db->prepare('SELECT * FROM ' . Schema::table('bids') . " {$where} ORDER BY id ASC", $args);
        $rows = $this->db->get_results($sql, ARRAY_A) ?: [];

        return array_map([$this, 'hydrate'], $rows);
    }

    /** @param array<string, string|null> $row */
    private function hydrate(array $row): Bid
    {
        return new Bid(
            id: (int) $row['id'],
            tenderId: (int) $row['tender_id'],
            contractorId: (int) $row['contractor_id'],
            priceExclVatCents: (int) $row['price_excl_vat_cents'],
            startDate: Rows::toDate($row['start_date']),
            completionDate: Rows::toDate($row['completion_date']),
            warrantyMonths: (int) $row['warranty_months'],
            description: (string) $row['description'],
            referencesText: (string) $row['references_text'],
            status: BidStatus::from((string) $row['status']),
            submittedAt: Rows::toDateTime($row['submitted_at']),
            updatedAt: Rows::toDateTime($row['updated_at']),
        );
    }
}
