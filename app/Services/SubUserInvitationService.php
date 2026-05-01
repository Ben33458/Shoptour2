<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\SubUserInvitationMail;
use App\Models\Pricing\Customer;
use App\Models\SubUser;
use App\Models\SubUserInvitation;
use App\Models\User;
use App\Services\Admin\AuditLogService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SubUserInvitationService
{
    /**
     * Erstellt einen Einladungs-Token und sendet die Einladungsmail.
     */
    public function invite(
        Customer $parentCustomer,
        string $email,
        string $firstName,
        string $lastName,
        array $permissions,
    ): SubUserInvitation {
        // Vorherige unbenutzte Einladungen für diese E-Mail + Kunde ungültig machen
        SubUserInvitation::where('parent_customer_id', $parentCustomer->id)
            ->where('email', $email)
            ->whereNull('used_at')
            ->delete();

        $plainToken = Str::random(48);

        $invitation = SubUserInvitation::create([
            'email'              => $email,
            'first_name'         => $firstName,
            'last_name'          => $lastName,
            'parent_customer_id' => $parentCustomer->id,
            'permissions'        => $permissions,
            'token'              => hash('sha256', $plainToken),
            'expires_at'         => now()->addHours(48),
        ]);

        Mail::to($email)->send(new SubUserInvitationMail($invitation, $plainToken, $parentCustomer));

        app(AuditLogService::class)->log('subuser.invitation.sent', $parentCustomer, [
            'recipient'          => $email,
            'invited_name'       => $firstName . ' ' . $lastName,
            'parent_customer_nr' => $parentCustomer->customer_number,
            'expires_at'         => $invitation->expires_at->toIso8601String(),
        ]);

        return $invitation;
    }

    /**
     * Löst eine Einladung ein: erstellt User + SubUser-Verknüpfung.
     *
     * @throws \InvalidArgumentException wenn Token ungültig/abgelaufen/benutzt
     */
    public function accept(string $plainToken, string $password): User
    {
        $hashedToken = hash('sha256', $plainToken);

        $invitation = SubUserInvitation::where('token', $hashedToken)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->with('parentCustomer')
            ->first();

        if (! $invitation) {
            throw new \InvalidArgumentException('Einladungslink ungültig oder abgelaufen.');
        }

        // E-Mail bereits vergeben?
        if (User::where('email', $invitation->email)->exists()) {
            throw new \InvalidArgumentException('Diese E-Mail-Adresse ist bereits registriert.');
        }

        $user = User::create([
            'first_name' => $invitation->first_name,
            'last_name'  => $invitation->last_name,
            'email'      => $invitation->email,
            'password'   => Hash::make($password),
            'role'       => User::ROLE_SUB_USER,
        ]);

        SubUser::create([
            'user_id'            => $user->id,
            'parent_customer_id' => $invitation->parent_customer_id,
            'permissions'        => $invitation->permissions,
            'active'             => true,
        ]);

        $invitation->update(['used_at' => now()]);

        return $user;
    }

    /**
     * Findet eine gültige Einladung anhand des Plain-Tokens.
     */
    public function findValid(string $plainToken): ?SubUserInvitation
    {
        return SubUserInvitation::where('token', hash('sha256', $plainToken))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->with('parentCustomer')
            ->first();
    }
}
