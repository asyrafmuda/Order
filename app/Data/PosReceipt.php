<?php

namespace App\Data;

final readonly class PosReceipt
{
    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $response
     */
    public function __construct(
        public string $receiptNumber,
        public array $request = [],
        public array $response = [],
    ) {}
}
