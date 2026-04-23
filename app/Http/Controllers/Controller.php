<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Throwable;

abstract class Controller
{
    use AuthorizesRequests;

    protected function backWithError(string $message, ?Throwable $exception = null): RedirectResponse
    {
        if ($exception) {
            report($exception);
        }

        return back()
            ->withInput()
            ->with('error', $message);
    }
}
