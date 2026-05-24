<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Vo;

enum TonComputeSkipReason: string
{
    case NoState   = 'no_state';
    case BadState  = 'bad_state';
    case NoGas     = 'no_gas';
    case Suspended = 'suspended';
}
