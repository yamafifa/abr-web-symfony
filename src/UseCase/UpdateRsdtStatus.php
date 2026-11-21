<?php

namespace App\UseCase;

/**
 * UpdateRsdt の結果
 */
enum UpdateRsdtStatus
{
    case Updated;
    case NotFound;
}
