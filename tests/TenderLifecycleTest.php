<?php

declare(strict_types=1);

namespace Kilpailutus\Tests;

use Kilpailutus\Domain\Exception\Conflict;
use Kilpailutus\Domain\Exception\Forbidden;
use Kilpailutus\Domain\Exception\NotFound;
use Kilpailutus\Domain\Exception\ValidationError;
use Kilpailutus\Tests\Support\ServiceTestCase;

final class TenderLifecycleTest extends ServiceTestCase
{
    public function test_only_housing_company_can_create_tender(): void
    {
        $this->expectDomainError(Forbidden::class, fn () => $this->tenders->create($this->contractorA, $this->tenderData()));
        $this->expectDomainError(Forbidden::class, fn () => $this->tenders->create($this->admin, $this->tenderData()));
    }

    public function test_new_tender_is_draft_and_deadline_is_interpreted_as_finnish_time(): void
    {
        $tender = $this->tenders->create($this->board, $this->tenderData());

        $this->assertSame('draft', $tender['status']);
        $this->assertSame('2026-11-20T10:00:00Z', $tender['deadline']);
        $this->assertTrue($tender['is_owner']);
    }

    public function test_validation_reports_each_invalid_field(): void
    {
        try {
            $this->tenders->create($this->board, $this->tenderData([
                'title' => '',
                'renovation_type' => 'avaruusasema',
                'postal_code' => '123',
                'desired_end' => '2027-01-01',
                'deadline' => '2026-02-30T12:00',
            ]));
            $this->fail('Odotettiin validointivirhettä');
        } catch (ValidationError $e) {
            $this->assertEqualsCanonicalizing(
                ['title', 'renovation_type', 'postal_code', 'desired_end', 'deadline'],
                array_keys($e->fieldErrors()),
            );
        }
    }

    public function test_html_is_stripped_from_text_fields(): void
    {
        $tender = $this->tenders->create($this->board, $this->tenderData(['title' => '<script>x()</script>Ikkunat']));

        $this->assertSame('x()Ikkunat', $tender['title']);
    }

    public function test_draft_is_hidden_from_contractors(): void
    {
        $id = $this->tenders->create($this->board, $this->tenderData())['id'];

        $this->expectDomainError(NotFound::class, fn () => $this->tenders->get($this->contractorA, $id));
        $this->assertSame([], $this->tenders->listFor($this->contractorA));
    }

    public function test_publish_requires_minimum_bid_period(): void
    {
        $id = $this->tenders->create($this->board, $this->tenderData(['deadline' => '2026-11-02T12:00']))['id'];

        $this->expectDomainError(Conflict::class, fn () => $this->tenders->publish($this->board, $id));
    }

    public function test_only_owner_can_publish(): void
    {
        $id = $this->tenders->create($this->board, $this->tenderData())['id'];

        $this->expectDomainError(Forbidden::class, fn () => $this->tenders->publish($this->admin, $id));
        $this->expectDomainError(NotFound::class, fn () => $this->tenders->publish($this->otherBoard, $id));
    }

    public function test_published_tender_cannot_be_edited_so_all_bidders_get_same_information(): void
    {
        $id = $this->publishedTenderId();

        $this->expectDomainError(Conflict::class, fn () => $this->tenders->update($this->board, $id, ['title' => 'Uusi otsikko']));
    }

    public function test_draft_can_be_partially_updated(): void
    {
        $id = $this->tenders->create($this->board, $this->tenderData())['id'];
        $updated = $this->tenders->update($this->board, $id, ['title' => 'Uusi otsikko', 'desired_start' => '']);

        $this->assertSame('Uusi otsikko', $updated['title']);
        $this->assertNull($updated['desired_start']);
        $this->assertSame('Helsinki', $updated['municipality']);
    }

    public function test_deadline_can_only_be_extended_while_open(): void
    {
        $id = $this->publishedTenderId();

        $this->expectDomainError(Conflict::class, fn () => $this->tenders->extendDeadline($this->board, $id, ['deadline' => '2026-11-15T12:00']));
        $extended = $this->tenders->extendDeadline($this->board, $id, ['deadline' => '2026-11-27T12:00']);
        $this->assertSame('2026-11-27T10:00:00Z', $extended['deadline']);

        $this->clock->set('2026-11-28T00:00:00Z');
        $this->expectDomainError(Conflict::class, fn () => $this->tenders->extendDeadline($this->board, $id, ['deadline' => '2026-12-27T12:00']));
    }

    public function test_award_only_after_deadline_and_contractor_learns_only_own_result(): void
    {
        $id = $this->publishedTenderId();
        $bidA = $this->bids->submit($this->contractorA, $id, $this->bidData());
        $this->bids->submit($this->contractorB, $id, $this->bidData());

        $this->expectDomainError(Conflict::class, fn () => $this->tenders->award($this->board, $id, $bidA['id']));

        $this->clock->set(self::AFTER_DEADLINE);
        $awarded = $this->tenders->award($this->board, $id, $bidA['id']);

        $this->assertSame('awarded', $awarded['phase']);
        $this->assertTrue($this->tenders->get($this->contractorA, $id)['own_bid']['awarded']);
        $viewB = $this->tenders->get($this->contractorB, $id);
        $this->assertFalse($viewB['own_bid']['awarded']);
        $this->assertArrayNotHasKey('awarded_bid_id', $viewB);
    }

    public function test_cannot_award_bid_of_another_tender(): void
    {
        $id1 = $this->publishedTenderId();
        $id2 = $this->publishedTenderId();
        $bidOther = $this->bids->submit($this->contractorA, $id2, $this->bidData());
        $this->clock->set(self::AFTER_DEADLINE);

        $this->expectDomainError(NotFound::class, fn () => $this->tenders->award($this->board, $id1, $bidOther['id']));
    }

    public function test_phase_changes_to_evaluation_at_deadline_without_cron(): void
    {
        $id = $this->publishedTenderId();
        $this->assertSame('open', $this->tenders->get($this->board, $id)['phase']);

        $this->clock->set(self::AFTER_DEADLINE);
        $this->assertSame('evaluation', $this->tenders->get($this->board, $id)['phase']);
    }

    public function test_listing_per_role(): void
    {
        $open = $this->publishedTenderId();
        $closedWithBid = $this->publishedTenderId();
        $this->bids->submit($this->contractorA, $closedWithBid, $this->bidData());
        $this->tenders->extendDeadline($this->board, $open, ['deadline' => '2026-12-20T12:00']);
        $draft = $this->tenders->create($this->board, $this->tenderData())['id'];
        $this->tenders->create($this->otherBoard, $this->tenderData());

        $this->clock->set(self::AFTER_DEADLINE);

        $this->assertEqualsCanonicalizing([$open, $closedWithBid, $draft], array_column($this->tenders->listFor($this->board), 'id'));
        $this->assertEqualsCanonicalizing([$open, $closedWithBid], array_column($this->tenders->listFor($this->contractorA), 'id'));
        $this->assertSame([$open], array_column($this->tenders->listFor($this->contractorB), 'id'));
        $this->assertCount(4, $this->tenders->listFor($this->admin));
    }
}
