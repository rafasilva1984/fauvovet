<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as Base;

/**
 * Proteção CSRF do Laravel (token + verificação de origem) sem o cookie XSRF-TOKEN legível por
 * JavaScript: os formulários usam o campo _token, então o cookie é superfície desnecessária.
 */
final class PreventRequestForgery extends Base
{
    protected $addHttpCookie = false;
}
