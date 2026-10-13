<?php

declare(strict_types=1);

namespace Kilpailutus\Infrastructure\WordPress;

use Kilpailutus\Domain\Actor;
use Kilpailutus\Domain\Role;

/** WordPress-roolit ja niiden kääntäminen palvelun loogisiksi rooleiksi. */
final class Roles
{
    public const HOUSING_COMPANY = 'tk_taloyhtio';
    public const CONTRACTOR = 'tk_urakoitsija';

    public const CAP_CREATE_TENDERS = 'tk_create_tenders';
    public const CAP_SUBMIT_BIDS = 'tk_submit_bids';
    public const CAP_MANAGE = 'tk_manage';

    public static function install(): void
    {
        add_role(self::HOUSING_COMPANY, 'Taloyhtiö / isännöitsijä', [
            'read' => true,
            self::CAP_CREATE_TENDERS => true,
        ]);
        add_role(self::CONTRACTOR, 'Urakoitsija', [
            'read' => true,
            self::CAP_SUBMIT_BIDS => true,
        ]);
        get_role('administrator')?->add_cap(self::CAP_MANAGE);
    }

    public static function uninstall(): void
    {
        remove_role(self::HOUSING_COMPANY);
        remove_role(self::CONTRACTOR);
        get_role('administrator')?->remove_cap(self::CAP_MANAGE);
    }

    /** Kirjautuneen käyttäjän toimija, tai null jos ei kirjautunut. */
    public static function currentActor(): ?Actor
    {
        $user = wp_get_current_user();
        if (!$user->exists()) {
            return null;
        }
        $roles = [];
        if (user_can($user, self::CAP_CREATE_TENDERS)) {
            $roles[] = Role::HOUSING_COMPANY;
        }
        if (user_can($user, self::CAP_SUBMIT_BIDS)) {
            $roles[] = Role::CONTRACTOR;
        }
        if (user_can($user, self::CAP_MANAGE)) {
            $roles[] = Role::ADMIN;
        }

        return new Actor((int) $user->ID, $roles);
    }
}
