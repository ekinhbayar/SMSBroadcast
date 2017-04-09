<?php

namespace ekinhbayar\SMSBroadcast;

class Limits
{
    /**
     * Maximum number of characters that can be included in a single SMS.
     */
    const MAX_CHARS_PER_MESSAGE_SINGLE = 160;

    /**
     * Maximum number of characters that can be included in each SMS when sending multipart SMSes.
     */
    const MAX_CHARS_PER_MESSAGE_MULTI = 153;

    /**
     * Maximum number of SMSes that can be part of a multipart SMS.
     */
    const MAX_SMS_PER_MULTIPART = 7;

    /**
     * Maximum number of characters that can be included in the sender string.
     */
    const MAX_CHARS_SENDER = 11;
}
