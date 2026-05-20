<?php

namespace App\Enums;

enum UserStatus: string
{
    case PendingEmail = 'pending_email';
    case PendingProfile = 'pending_profile';
    case Active = 'active';
}
