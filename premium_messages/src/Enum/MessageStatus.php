<?php

namespace App\Enum;

enum MessageStatus: string
{
    case Draft = 'draft';
    case Verified = 'verified';
    case Sent = 'sent';
    case Rejected = 'rejected';
}
