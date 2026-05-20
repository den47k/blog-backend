<?php

namespace App\Enums;

enum TokenAbility: string
{
    case Full = '*';
    case RegisterPending = 'register:pending';
    case TwoFactorPending = '2fa:pending';
    case OAuthLinkPending = 'oauth:link:pending';
}
