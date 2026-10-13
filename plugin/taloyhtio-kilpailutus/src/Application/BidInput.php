<?php

declare(strict_types=1);

namespace Kilpailutus\Application;

use Kilpailutus\Domain\Exception\ValidationError;

/** Tarjouslomakkeen validointi. */
final class BidInput
{
    /**
     * @param array<string, mixed> $data
     * @return array{priceExclVatCents:int, startDate:?\DateTimeImmutable, completionDate:?\DateTimeImmutable, warrantyMonths:int, description:string, referencesText:string}
     */
    public static function validate(array $data): array
    {
        $in = new Input($data);
        $fields = [
            'priceExclVatCents' => $in->euroCents('price_excl_vat', true),
            'startDate' => $in->date('start_date'),
            'completionDate' => $in->date('completion_date'),
            'warrantyMonths' => $in->int('warranty_months', 0, 240, 24),
            'description' => $in->text('description', true, 20000),
            'referencesText' => $in->text('references', false, 10000),
        ];
        if ($fields['startDate'] && $fields['completionDate'] && $fields['completionDate'] < $fields['startDate']) {
            $in->addError('completion_date', 'Valmistumisen on oltava aloituksen jälkeen.');
        }
        if ($in->errors() !== []) {
            throw new ValidationError($in->errors());
        }

        /** @var array{priceExclVatCents:int, startDate:?\DateTimeImmutable, completionDate:?\DateTimeImmutable, warrantyMonths:int, description:string, referencesText:string} $fields */
        return $fields;
    }
}
