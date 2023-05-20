<?php

namespace Lapaliv\BulkUpsert\Features;

class GetValueHashFeature
{
    public function handle(mixed $value): string
    {
        return hash('crc32c', $value . ':' . gettype($value));
    }
}
