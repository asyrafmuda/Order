<?php

namespace App\Support\Licensing;

enum LicenseStatus: string
{
    /** No check here: licensing is not set up, a local address, or the license server itself. */
    case NotRequired = 'not_required';
    case Valid = 'valid';
    /** Valid; a fresh certificate is due. */
    case Stale = 'stale';
    /** Valid, but the license server has not answered for a while. */
    case Warning = 'warning';
    case Missing = 'missing';
    case WrongDomain = 'wrong_domain';
    case Revoked = 'revoked';
    /** The license server has not answered for too long. */
    case Expired = 'expired';
    case Invalid = 'invalid';

    /**
     * Whether the site may be used.
     */
    public function allowsUse(): bool
    {
        return in_array($this, [self::NotRequired, self::Valid, self::Stale, self::Warning], true);
    }

    /**
     * Whether a fresh certificate should be fetched.
     */
    public function wantsRefresh(): bool
    {
        return in_array($this, [self::Stale, self::Warning, self::Expired, self::Revoked], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::NotRequired => 'No license needed here',
            self::Valid, self::Stale => 'Active',
            self::Warning => 'Active — license server not reachable',
            self::Missing => 'Not activated',
            self::WrongDomain => 'Activated for another domain',
            self::Revoked => 'Cancelled',
            self::Expired => 'Could not be confirmed',
            self::Invalid => 'Not valid',
        };
    }

    /**
     * What the site owner should do.
     */
    public function help(): string
    {
        return match ($this) {
            self::NotRequired, self::Valid, self::Stale => '',
            self::Warning => 'The license server has not answered for a while. Check that this server can make outgoing HTTPS connections; the site stops if it cannot reach the server for '.config('licensing.lock_after_days').' days.',
            self::Missing => 'Enter the purchase code you received with the app.',
            self::WrongDomain => 'This copy was activated for another web address. Enter the purchase code for this address, or release it on the old site first.',
            self::Revoked => 'The seller cancelled this license. Contact them, or enter a different purchase code.',
            self::Expired => 'The license could not be confirmed for '.config('licensing.lock_after_days').' days because the license server could not be reached. Press “Check now” once this server can make outgoing HTTPS connections.',
            self::Invalid => 'The license file is damaged. Enter your purchase code again.',
        };
    }
}
