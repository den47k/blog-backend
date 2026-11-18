<?php

namespace App\Enums;

enum AuthStatus: string
{
    case Authenticated = 'authenticated';
    case TwoFactorRequired = 'two_factor_required';
    case LinkRequired = 'link_required';
    case PendingEmail = 'pending_email';
    case PendingProfile = 'pending_profile';
}
