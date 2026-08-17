<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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
 */
class UserProvisioningService
{
    public function provision(string $name, string $email, string $roleName): array
    {
        $password = $this->generateTemporaryPassword();

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'status' => 'active',
            'must_change_password' => true,
        ]);

        $user->assignRole($roleName);

        $this->audit($user, 'provisioned');

        return ['user' => $user, 'temporaryPassword' => $password];
    }

    public function resetPassword(User $user): string
    {
        $password = $this->generateTemporaryPassword();

        $user->update([
            'password' => $password,
            'must_change_password' => true,
            'remember_token' => Str::random(60),
        ]);

        DB::table('sessions')->where('user_id', $user->id)->delete();

        $this->audit($user, 'password_reset');

        return $password;
    }

    public function generateTemporaryPassword(): string
    {
        // Str::password() uses random_bytes() under the hood -- never a
        // predictable pattern, never derived from NIK/NISN/date-of-birth.
        return Str::password(16);
    }

    private function audit(User $user, string $action): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'old_values' => null,
            'new_values' => ['must_change_password' => true],
            'ip_address' => request()->ip(),
        ]);
    }
}
