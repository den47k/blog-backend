<?php

namespace App\Enums;

enum AuthStatus: string
{
    case LoggedIn = 'logged_in';
    case TwoFactorRequired = '2fa_required';
    case LinkRequired = 'link_required';
    case PendingEmail = 'pending_email';
    case PendingProfile = 'pending_profile';
}
