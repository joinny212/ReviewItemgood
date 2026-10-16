<?php

function normalizeProductPrice(string $price): ?string
{
    $parts = preg_split('/\s*[-–—]\s*/u', trim($price));
    if (!$parts || count($parts) > 2) {
        return null;
    }

    $normalized = [];
    foreach ($parts as $part) {
        $amount = normalizeProductPriceAmount($part);
        if ($amount === null) {
            return null;
        }
        $normalized[] = $amount;
    }

    if (count($normalized) === 2 && (float) $normalized[0] > (float) $normalized[1]) {
        return null;
    }

    return implode(' - ', $normalized);
}

function formatProductPrice(string|int|float $price): string
{
    $normalized = normalizeProductPrice((string) $price);
    if ($normalized === null) {
        return (string) $price . '₫';
    }

    $parts = explode(' - ', $normalized);
    return implode(' - ', array_map(
        static fn(string $amount): string => number_format((float) $amount, 0, ',', '.') . '₫',
        $parts
    ));
}

function normalizeProductPriceAmount(string $amount): ?string
{
    $amount = preg_replace('/[₫đ\s]/iu', '', trim($amount));
    if ($amount === null || $amount === '') {
        return null;
    }

    if (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $amount)) {
        $amount = str_replace('.', '', $amount);
    } elseif (preg_match('/^\d{1,3}(?:,\d{3})+$/', $amount)) {
        $amount = str_replace(',', '', $amount);
    } elseif (!preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
        return null;
    }

    if (!is_numeric($amount) || (float) $amount <= 0) {
        return null;
    }

    return $amount;
}
