<?php

namespace Eventrel\Client\Enums;

enum WebhookMode: string
{
    case BIDIRECTIONAL = 'bidirectional';
    case INBOUND = 'inbound';
    case OUTBOUND = 'outbound';
}
