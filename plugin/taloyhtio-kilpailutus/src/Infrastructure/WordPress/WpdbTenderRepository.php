<?php

declare(strict_types=1);

namespace Kilpailutus\Infrastructure\WordPress;

use Kilpailutus\Domain\Repository\TenderRepository;
use Kilpailutus\Domain\Tender;
use Kilpailutus\Domain\TenderStatus;

final class WpdbTenderRepository implements TenderRepository
{
    public function __construct(private readonly \wpdb $db)
    {
    }

    public function find(int $id): ?Tender
    {
        $row = $this->db->get_row($this->db->prepare('SELECT * FROM ' . Schema::table('tenders') . ' WHERE id = %d', $id), ARRAY_A);

        return $row ? $this->hydrate($row) : null;
    }

    public function save(Tender $tender): Tender
    {
        $data = [
            'owner_id' => $tender->ownerId,
            'title' => $tender->title,
            'renovation_type' => $tender->renovationType,
            'description' => $tender->description,
            'housing_company' => $tender->housingCompany,
            'street_address' => $tender->streetAddress,
            'postal_code' => $tender->postalCode,
            'municipality' => $tender->municipality,
            'desired_start' => Rows::fromDate($tender->desiredStart),
            'desired_end' => Rows::fromDate($tender->desiredEnd),
            'deadline_at' => Rows::fromDateTime($tender->deadlineAt),
            'status' => $tender->status->value,
            'awarded_bid_id' => $tender->awardedBidId,
            'published_at' => Rows::fromDateTime($tender->publishedAt),
            'cancelled_at' => Rows::fromDateTime($tender->cancelledAt),
            'created_at' => Rows::fromDateTime($tender->createdAt),
            'updated_at' => Rows::fromDateTime($tender->updatedAt),
        ];
        $formats = ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s'];

        if ($tender->id === null) {
            if ($this->db->insert(Schema::table('tenders'), $data, $formats) === false) {
                throw new \RuntimeException('Kilpailutuksen tallennus epäonnistui: ' . $this->db->last_error);
            }
            $tender->id = (int) $this->db->insert_id;
        } elseif ($this->db->update(Schema::table('tenders'), $data, ['id' => $tender->id], $formats, ['%d']) === false) {
            throw new \RuntimeException('Kilpailutuksen tallennus epäonnistui: ' . $this->db->last_error);
        }

        return $tender;
    }

    public function findByOwner(int $ownerId): array
    {
        return $this->query('WHERE owner_id = %d', [$ownerId]);
    }

    public function findOpen(\DateTimeImmutable $now): array
    {
        return $this->query('WHERE status = %s AND deadline_at > %s', [TenderStatus::Published->value, Rows::fromDateTime($now)]);
    }

    public function findByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        return $this->query("WHERE id IN ({$placeholders})", $ids);
    }

    public function findAll(): array
    {
        return $this->query('', []);
    }

    /** @param list<scalar> $args @return list<Tender> */
    private function query(string $where, array $args): array
    {
        $sql = 'SELECT * FROM ' . Schema::table('tenders') . " {$where} ORDER BY deadline_at ASC";
        if ($args !== []) {
            $sql = $this->db->prepare($sql, $args);
        }
        $rows = $this->db->get_results($sql, ARRAY_A) ?: [];

        return array_map([$this, 'hydrate'], $rows);
    }

    /** @param array<string, string|null> $row */
    private function hydrate(array $row): Tender
    {
        return new Tender(
            id: (int) $row['id'],
            ownerId: (int) $row['owner_id'],
            title: (string) $row['title'],
            renovationType: (string) $row['renovation_type'],
            description: (string) $row['description'],
            housingCompany: (string) $row['housing_company'],
            streetAddress: (string) $row['street_address'],
            postalCode: (string) $row['postal_code'],
            municipality: (string) $row['municipality'],
            desiredStart: Rows::toDate($row['desired_start']),
            desiredEnd: Rows::toDate($row['desired_end']),
            deadlineAt: Rows::toDateTime($row['deadline_at']),
            status: TenderStatus::from((string) $row['status']),
            awardedBidId: $row['awarded_bid_id'] !== null ? (int) $row['awarded_bid_id'] : null,
            publishedAt: Rows::toDateTime($row['published_at']),
            cancelledAt: Rows::toDateTime($row['cancelled_at']),
            createdAt: Rows::toDateTime($row['created_at']),
            updatedAt: Rows::toDateTime($row['updated_at']),
        );
    }
}
