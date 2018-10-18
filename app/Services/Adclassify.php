<?php


namespace Adshares\Adserver\Services;

class Adclassify
{
    public function send(int $campaingId, ?array $targetingRequires, ?array $targetingExcludes, ?array $bannerUrls)
    {
        return ['18+', 'casino'];
    }
}
