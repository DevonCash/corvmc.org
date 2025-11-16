<?php

namespace App\Support;

/**
 * Trust System Constants
 */
class TrustConstants
{
    /**
     * Trust level thresholds
     */
    public const TRUST_TRUSTED = 5;

    public const TRUST_VERIFIED = 15;

    public const TRUST_AUTO_APPROVED = 30;

    /**
     * Trust point values
     */
    public const POINTS_SUCCESSFUL_CONTENT = 1;

    public const POINTS_MINOR_VIOLATION = -3;

    public const POINTS_MAJOR_VIOLATION = -5;

    public const POINTS_SPAM_VIOLATION = -10;
}
