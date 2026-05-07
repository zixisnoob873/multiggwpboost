<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;

abstract class Controller
{
    use AuthorizesRequests;
    use DispatchesJobs;
}
