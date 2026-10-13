<?php

declare(strict_types=1);

namespace Kilpailutus\Domain;

enum BidStatus: string
{
    case Submitted = 'submitted';
    case Withdrawn = 'withdrawn';
}
