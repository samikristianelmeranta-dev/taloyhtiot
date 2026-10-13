<?php

declare(strict_types=1);

namespace Kilpailutus\Domain;

/** Käyttöliittymälle näytettävä vaihe, johdettu tilasta ja määräpäivästä. */
enum TenderPhase: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Evaluation = 'evaluation';
    case Awarded = 'awarded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Luonnos',
            self::Open => 'Tarjousaika käynnissä',
            self::Evaluation => 'Tarjoukset avattu',
            self::Awarded => 'Urakoitsija valittu',
            self::Cancelled => 'Peruttu',
        };
    }
}
