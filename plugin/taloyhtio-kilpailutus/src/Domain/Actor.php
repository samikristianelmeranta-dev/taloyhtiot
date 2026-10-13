<?php

declare(strict_types=1);

namespace Kilpailutus\Domain;

/** Kirjautunut käyttäjä, jonka puolesta toiminto tehdään. */
final class Actor
{
    /** @param list<string> $roles Role::*-vakioita */
    public function __construct(
        public readonly int $userId,
        private readonly array $roles,
    ) {
    }

    public function is(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function isHousingCompany(): bool
    {
        return $this->is(Role::HOUSING_COMPANY);
    }

    public function isContractor(): bool
    {
        return $this->is(Role::CONTRACTOR);
    }

    public function isAdmin(): bool
    {
        return $this->is(Role::ADMIN);
    }

    /** @return list<string> */
    public function roles(): array
    {
        return $this->roles;
    }
}
