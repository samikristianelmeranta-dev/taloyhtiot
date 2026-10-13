<?php

declare(strict_types=1);

namespace Kilpailutus\Application;

use Kilpailutus\Domain\Exception\ValidationError;
use Kilpailutus\Domain\RenovationType;
use Kilpailutus\Domain\Tender;

/** Kilpailutuslomakkeen validointi ja kenttien vienti Tender-olioon. */
final class TenderInput
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed> Tender-olion ominaisuudet
     */
    public static function validate(array $data, bool $partial): array
    {
        $in = new Input($data);
        $fields = [];
        $required = !$partial;

        $map = [
            'title' => fn () => $in->text('title', $required, 200, 3),
            'renovation_type' => fn () => $in->oneOf('renovation_type', array_keys(RenovationType::ALL), $required),
            'description' => fn () => $in->text('description', $required, 20000),
            'housing_company' => fn () => $in->text('housing_company', $required, 200),
            'street_address' => fn () => $in->text('street_address', $required, 200),
            'postal_code' => fn () => $in->text('postal_code', $required, 5),
            'municipality' => fn () => $in->text('municipality', $required, 100),
            'desired_start' => fn () => $in->date('desired_start'),
            'desired_end' => fn () => $in->date('desired_end'),
            'deadline' => fn () => $in->dateTime('deadline', $required),
        ];
        $props = [
            'title' => 'title',
            'renovation_type' => 'renovationType',
            'description' => 'description',
            'housing_company' => 'housingCompany',
            'street_address' => 'streetAddress',
            'postal_code' => 'postalCode',
            'municipality' => 'municipality',
            'desired_start' => 'desiredStart',
            'desired_end' => 'desiredEnd',
            'deadline' => 'deadlineAt',
        ];

        foreach ($map as $key => $read) {
            if ($partial && !$in->has($key)) {
                continue;
            }
            $fields[$props[$key]] = $read();
        }

        if (isset($fields['postalCode']) && $fields['postalCode'] !== '' && !preg_match('/^\d{5}$/', $fields['postalCode'])) {
            $in->addError('postal_code', 'Postinumerossa on 5 numeroa.');
        }
        if (($fields['desiredStart'] ?? null) && ($fields['desiredEnd'] ?? null) && $fields['desiredEnd'] < $fields['desiredStart']) {
            $in->addError('desired_end', 'Valmistumisen on oltava aloituksen jälkeen.');
        }

        if ($in->errors() !== []) {
            throw new ValidationError($in->errors());
        }

        return $fields;
    }

    /** @param array<string, mixed> $fields */
    public static function apply(Tender $tender, array $fields): void
    {
        foreach ($fields as $prop => $value) {
            $tender->{$prop} = $value;
        }
    }
}
