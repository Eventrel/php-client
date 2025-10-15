<?php

namespace Eventrel\Enums;

enum WebhookMode: string
{
    case BIDIRECTIONAL = 'bidirectional';
    case INBOUND = 'inbound';
    case OUTBOUND = 'outbound';
}
