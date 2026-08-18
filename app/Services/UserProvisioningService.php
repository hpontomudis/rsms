<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The one write path for creating a login account with a temporary
 * password, and for administratively resetting one (P2B/P2C). Both
 * operations share the same shape: generate a cryptographically secure
 * random password server-side, hash it via User's existing `hashed` cast,
 * force must_change_password=true, invalidate the account's other
 * sessions, and record who did it without ever writing the password
 * itself anywhere -- not the database, not the audit log, not a log file.
 *
 * The plaintext temporary password is returned to the caller ONLY as a
 * return value, for the current request to display or export once. Never
 * stored. Never logged.
 *
 * Both methods take the acting User and check AccountAuthorizationMatrix
 * UNCONDITIONALLY (P2.1) -- not merely relying on the caller having
 * already checked a Policy/Gate, which `super_admin`'s `Gate::before`
 * bypass would make unreliable for the "no super_admin -> super_admin
 * reset via ordinary UI" rule specifically. See
 * AccountAuthorizationMatrix's docblock.
 */
class UserProvisioningService
{
    public function provision(User $actor, string $name, string $email, string $roleName): array
    {
        if (! AccountAuthorizationMatrix::canProvision($actor, $roleName)) {
            throw new AuthorizationException("Your account may not provision a \"{$roleName}\" login.");
        }

        $password = $this->generateTemporaryPassword();

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'status' => 'active',
            'must_change_password' => true,
        ]);

        $user->assignRole($roleName);

        $this->audit($actor, $user, 'provisioned');

        return ['user' => $user, 'temporaryPassword' => $password];
    }

    public function resetPassword(User $actor, User $target): string
    {
        if (! AccountAuthorizationMatrix::canResetPasswordFor($actor, $target)) {
            throw new AuthorizationException('Your account may not reset this login.');
        }

        $password = $this->generateTemporaryPassword();

        $target->update([
            'password' => $password,
            'must_change_password' => true,
            'remember_token' => Str::random(60),
        ]);

        DB::table('sessions')->where('user_id', $target->id)->delete();

        $this->audit($actor, $target, 'password_reset');

        return $password;
    }

    public function generateTemporaryPassword(): string
    {
        // Str::password() uses random_bytes() under the hood -- never a
        // predictable pattern, never derived from NIK/NISN/date-of-birth.
        return Str::password(16);
    }

    private function audit(User $actor, User $target, string $action): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => User::class,
            'auditable_id' => $target->id,
            'old_values' => null,
            'new_values' => ['must_change_password' => true],
            'ip_address' => request()->ip(),
        ]);
    }
}
