<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Vo;

enum TonTransactionStatus
{
    case Pending;
    case Success;
    case ComputePhaseFailed;
    case ActionPhaseFailed;
    case Aborted;
}
