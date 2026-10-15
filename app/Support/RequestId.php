<?php

namespace App\Support;

final class RequestId
{
    public static function current(): ?string
    {
        $id = request()->attributes->get('request_id');

        return is_string($id) ? $id : null;
    }
}
