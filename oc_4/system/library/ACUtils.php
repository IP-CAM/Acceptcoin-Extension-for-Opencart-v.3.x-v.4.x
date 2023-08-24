<?php

namespace Opencart\extension\acceptcoin\system\library;

class ACUtils
{
    public const FLOW_DATA_PROCESSED_AMOUNT = "processedAmountInUSD";

    /**
     * @param array $data
     * @return float
     */
    public static function getProcessedAmount(array $data): float
    {
        if (!isset($data['flowData'])) {
            return 0;
        }

        $processedAmount = array_filter($data['flowData'], function ($item) {
            return isset($item['name']) && $item['name'] === self::FLOW_DATA_PROCESSED_AMOUNT;
        });

        if (!count($processedAmount)) {
            return 0;
        }

        return $processedAmount[array_key_first($processedAmount)]['value'];
    }
}