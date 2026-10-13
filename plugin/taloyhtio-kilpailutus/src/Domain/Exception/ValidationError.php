<?php

declare(strict_types=1);

namespace Kilpailutus\Domain\Exception;

final class ValidationError extends DomainError
{
    /** @param array<string, string> $fieldErrors kenttä => virheilmoitus */
    public function __construct(private readonly array $fieldErrors)
    {
        parent::__construct('Tarkista lomakkeen tiedot.');
    }

    public function httpStatus(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'tk_validation';
    }

    /** @return array<string, string> */
    public function fieldErrors(): array
    {
        return $this->fieldErrors;
    }

    public function details(): array
    {
        return ['fields' => $this->fieldErrors];
    }
}
