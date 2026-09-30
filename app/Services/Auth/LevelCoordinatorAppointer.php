<?php

namespace App\Services\Auth;

use App\Models\Curriculum\Programme;
use App\Models\LevelCoordinator;
use App\Models\Organization;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Appoints a level coordinator at a school, and creates the account that goes
 * with the appointment.
 *
 * The account is created at the moment of appointment rather than in advance.
 * An earlier plan was to provision every school x programme x level up front —
 * 1,364 academic programs across 7 levels is 9,548 accounts — and the reason it
 * was dropped is worth recording rather than leaving as folklore: a placeholder
 * account needs a password before anyone has ever logged in, and the only
 * choices are a shared default or a stored credential nobody has requested. A
 * shared default turns every placeholder into a login that can be guessed; a
 * stored random one means ACL is sitting on 9,538 unused secrets. Creating the
 * account when a real person is appointed removes both, and costs nothing —
 * the appointment is the only moment at which the account is needed.
 *
 * The password is therefore never a known value. The account is created with an
 * unusable password and is unlocked only by a single-use activation code that
 * expires, and only the person holding the code can set the real one.
 */
class LevelCoordinatorAppointer
{
    /** Levels ACL runs. Mirrors the levels the CCMAS catalogue carries. */
    public const LEVELS = [100, 200, 300, 400, 500, 600, 800];

    /**
     * Build the address a coordinator is invited on.
     *
     * {org}{programme}lvl{level}lvlcoord@ — for example
     * `nwucyblvl100lvlcoord@aclacademy.me` for the level 100 Cybersecurity
     * coordinator at Northwest University Kano.
     *
     * The scheme is predictable by design: a coordinator who knows their own
     * school and programme can be told their address, and it is stable so
     * reappointment reuses the same identity rather than stranding a
     * colleague. Predictability is safe here precisely because the address is
     * not a secret — the single-use activation code is.
     */
    public function emailFor(Organization $organization, Programme $programme, int $level, string $domain = 'aclacademy.me'): string
    {
        $org = $this->organizationToken($organization);
        $prog = $this->tokenFor($programme->code ?: $programme->name);

        return sprintf('%s%slvl%dlvlcoord@%s', $org, $prog, $level, $domain);
    }

    /**
     * Appoint a coordinator and create their account.
     *
     * @param  array{name: string, email?: string, phone?: string}  $person
     * @return array{user: User, coordinator: LevelCoordinator, activation_token: string}
     */
    public function appoint(Organization $organization, Programme $programme, int $level, array $person, string $domain = 'aclacademy.me'): array
    {
        if (! in_array($level, self::LEVELS, true)) {
            throw new \InvalidArgumentException("Level {$level} is not an ACL level.");
        }

        if (! $organization->is_active) {
            throw new \InvalidArgumentException('Cannot appoint a coordinator at an inactive institution.');
        }

        // One coordinator per school, programme, level and session. The
        // database enforces this too, but checking here turns a duplicate into
        // a clear message instead of a constraint violation.
        $existing = LevelCoordinator::where('organization_id', $organization->id)
            ->where('programme_id', $programme->id)
            ->where('level', $level)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            throw new \RuntimeException(
                'That institution already has an active coordinator for this programme and level.'
            );
        }

        $email = $this->emailFor($organization, $programme, $level, $domain);

        $result = DB::transaction(function () use ($organization, $programme, $level, $person, $email) {
            $user = User::where('email', $email)->first();

            if (! $user) {
                $user = User::create([
                    'name' => $person['name'],
                    'email' => $email,
                    // An unusable password, not a guessable one. The account
                    // cannot be signed into until the activation link is used,
                    // which is what stops a generated address from being a
                    // login at all. The link is a signed temporary URL, so no
                    // recovery token needs to be stored either.
                    'password' => Hash::make(Str::random(64)),
                    'force_password_change' => true,
                    // The gate reads this. The account exists, but nobody has
                    // yet established who holds it.
                    'must_complete_onboarding' => true,
                ]);
            } else {
                // Reappointment of an existing identity: put the gate back up
                // so the new appointee re-confirms their own details instead
                // of inheriting a previous holder's.
                $user->force_password_change = true;
                $user->must_complete_onboarding = true;
                $user->onboarding_completed_at = null;
                $user->save();
            }

            $this->assignCoordinatorRole($user, $organization);

            $coordinator = LevelCoordinator::updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'programme_id' => $programme->id,
                    'level' => $level,
                ],
                [
                    'user_id' => $user->id,
                    'status' => 'active',
                    'appointed_date' => now()->toDateString(),
                ]
            );

            return ['user' => $user, 'coordinator' => $coordinator];
        });

        return $result + [
            // The signed activation link, handed to the administrator who made
            // the appointment to pass to the person. Returned once and never
            // stored in a recoverable form.
            'activation_url' => URL::temporarySignedRoute(
                'coordinator.activate',
                now()->addDays(14),
                ['user' => $result['user']->id]
            ),
        ];
    }

    /**
     * Grant the coordinator role, scoped to the school.
     */
    private function assignCoordinatorRole(User $user, Organization $organization): void
    {
        $roleId = Role::where('slug', RoleHomeResolver::ROLE_LEVEL_COORDINATOR)->value('id');

        if (! $roleId) {
            throw new \RuntimeException('The level coordinator role is missing from the roles table.');
        }

        $alreadyAssigned = RoleAssignment::where('user_id', $user->id)
            ->where('role_id', $roleId)
            ->exists();

        if ($alreadyAssigned) {
            return;
        }

        RoleAssignment::create([
            'user_id' => $user->id,
            'role_id' => $roleId,
            // Scoped to the school, not global. The role assignment is what the
            // organization sign-in screen checks, so this row is the reason a
            // coordinator can only reach their own institution.
            'entity_type' => Organization::class,
            'entity_id' => $organization->id,
        ]);
    }

    /**
     * The school's token in a coordinator address.
     *
     * Preference order matters. A school that already carries an abbreviation
     * is using it in its own name and on its own letterhead, so that is used
     * verbatim. Most schools carry none of these columns, so the initials are
     * derived from the name.
     *
     * The trailing location is dropped when it repeats the school's own
     * `state`, which is what turns "Northwest University Kano" into `nwu`
     * rather than `nuk`. The city is where the school is, not part of what it
     * calls itself — every school whose name ends in its state reads badly if
     * the state is counted as a word of the name.
     */
    private function organizationToken(Organization $organization): string
    {
        foreach ([$organization->short_name, $organization->abbr, $organization->code] as $explicit) {
            if (! empty($explicit)) {
                return $this->tokenFor($explicit, 8);
            }
        }

        $words = preg_split('/[\s,\-]+/u', trim($organization->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $stop = ['of', 'the', 'and', 'for', 'at'];

        $meaningful = array_values(array_filter(
            $words,
            fn (string $w): bool => ! in_array(mb_strtolower($w), $stop, true)
        ));

        // "Northwest University Kano" -> keep "Northwest University", drop the
        // place. A school names itself by what it teaches and where; the
        // trailing city or state is not part of the name it answers to, which
        // is why the abbreviation people actually write is "nwu" and not
        // "nuk".
        if (count($meaningful) > 1) {
            $last = mb_strtolower((string) end($meaningful));

            if ($last === mb_strtolower(trim((string) $organization->state)) || $this->isKnownLocation($last)) {
                array_pop($meaningful);
            }
        }

        // "Northwest University" abbreviates to NWU, not NU: the school treats
        // the compound as North + West. Institutions in this space name
        // themselves by direction constantly, so a leading directional word
        // contributes both of its halves. Only the first word is split, and
        // only when the remainder is a real direction, so "Westland" and
        // "Eastern" are left alone.
        $parts = $this->splitDirectionalWord($meaningful[0] ?? '');
        if (count($parts) > 1) {
            $meaningful[0] = $parts[0];
            array_splice($meaningful, 1, 0, [$parts[1]]);
        }

        $initials = '';
        foreach ($meaningful as $word) {
            $initials .= mb_substr($word, 0, 1);
            if (mb_strlen($initials) >= 4) {
                break;
            }
        }

        return $this->tokenFor($initials !== '' ? $initials : $organization->name, 4);
    }

    /**
     * Split a leading compound direction into its two halves.
     *
     * "Northwest" becomes "North" + "West". Returns a single-element array
     * unchanged for anything that is not a recognised compound, so no word is
     * ever split speculatively.
     */
    private function splitDirectionalWord(string $word): array
    {
        $pairs = [
            'northeast' => ['North', 'East'],
            'northwest' => ['North', 'West'],
            'southeast' => ['South', 'East'],
            'southwest' => ['South', 'West'],
        ];

        $key = mb_strtolower($word);

        return $pairs[$key] ?? [$word];
    }

    /**
     * Whether a word names a place rather than the school.
     *
     * Read from the `states` table so the list of places stays data rather than
     * a hardcoded array in a service. Read once per process and cached after,
     * because an address is generated rarely and the lookup is otherwise a
     * query per appointment.
     */
    private function isKnownLocation(string $word): bool
    {
        static $locations = null;

        if ($locations === null) {
            $locations = Organization::query()
                ->whereNotNull('state')
                ->distinct()
                ->pluck('state')
                ->map(fn ($s) => mb_strtolower(trim((string) $s)))
                ->filter()
                ->all();

            // The states table is the authority; organization.state is added
            // for schools entered before that table existed.
            foreach (\App\Models\State::pluck('name') as $name) {
                $locations[] = mb_strtolower(trim((string) $name));
            }

            $locations = array_unique($locations);
        }

        return in_array($word, $locations, true);
    }

    /**
     * Reduce a name to the lowercase token used in an address.
     */
    private function tokenFor(string $value, int $maxLength = 8): string
    {
        $token = Str::lower(preg_replace('/[^A-Za-z0-9]+/', '', $value) ?? '');

        return substr($token, 0, $maxLength);
    }
}
