<?php
declare(strict_types=1);

function calculate_block_count(float|int $area): int
{
    if ($area <= 0) {
        throw new InvalidArgumentException('Luas area wajib lebih dari 0.');
    }

    return (int) ceil($area / 10);
}

function calculate_duration_total(int $durationPer10m, int $blockCount): int
{
    if ($durationPer10m < 1 || $blockCount < 1) {
        throw new InvalidArgumentException('Durasi tidak valid.');
    }

    return $durationPer10m * $blockCount;
}

function calculate_material_total(array $materials, int $blockCount): int
{
    if ($blockCount < 1) {
        throw new InvalidArgumentException('Jumlah blok tidak valid.');
    }

    $total = 0;
    foreach ($materials as $material) {
        $qty = (float) ($material['quantity'] ?? $material['default_qty_per_10m'] ?? 0);
        $price = (float) ($material['unit_price'] ?? $material['price_per_unit'] ?? 0);
        if ($qty < 0 || $price < 0) {
            throw new InvalidArgumentException('Quantity dan harga bahan tidak boleh negatif.');
        }
        $total += (int) round($qty * $blockCount * $price);
    }

    return $total;
}

function calculate_labor_total(int $workerCount, int $days, float|int $dailyRate): int
{
    if ($workerCount < 1 || $days < 1 || $dailyRate < 0) {
        throw new InvalidArgumentException('Data tenaga kerja tidak valid.');
    }

    return (int) round($workerCount * $days * $dailyRate);
}

function calculate_additional_total(array $additionalItems): int
{
    $total = 0;
    foreach ($additionalItems as $item) {
        $qty = (float) ($item['quantity'] ?? 1);
        $price = (float) ($item['unit_price'] ?? $item['default_price'] ?? 0);
        if ($qty < 0 || $price < 0) {
            throw new InvalidArgumentException('Quantity dan harga tambahan tidak boleh negatif.');
        }
        $total += (int) round($qty * $price);
    }

    return $total;
}

function calculate_grand_total(int ...$parts): int
{
    foreach ($parts as $part) {
        if ($part < 0) {
            throw new InvalidArgumentException('Total tidak boleh negatif.');
        }
    }

    return array_sum($parts);
}
