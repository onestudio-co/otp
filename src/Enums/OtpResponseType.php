<?php

namespace OneStudio\Otp\Enums;

enum OtpResponseType: string
{
    case SUCCESS = 'success';
    case RATE_LIMITED = 'rate_limited';
    case BLOCKED = 'blocked';
    case RESEND_DELAY = 'resend_delay';
    case SEND_FAILED = 'send_failed';
}
