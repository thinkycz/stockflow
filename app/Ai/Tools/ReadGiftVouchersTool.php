<?php

declare(strict_types=1);

namespace App\Ai\Tools;

final class ReadGiftVouchersTool extends AbstractCatalogReadTool
{
    protected const string TOOL_NAME = 'read_gift_vouchers';

    protected const string TOOL_DESCRIPTION = 'Read voucher lifecycle metadata without voucher codes.';

    protected const string RESOURCE = 'gift_vouchers';
}
