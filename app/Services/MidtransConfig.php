<?php

namespace App\Services;

use Midtrans\Config;

Config::$serverKey = config('midtrans.serverKey');
Config::$isProduction = config('midtrans.isProduction');
Config::$isSanitized = config('midtrans.isSanitized');
Config::$is3ds = config('midtrans.is3ds');  
