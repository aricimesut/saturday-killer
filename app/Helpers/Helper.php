<?php

namespace App\Helpers;

use App\Http\Controllers\Cms\AyarController;
use App\Models\Cms\Ayarlar;
use App\Models\Cms\Firmalar;
use Illuminate\Support\Str;
use App\Models\Cms\Tanimlar;
use App\Support\Enums\Limits;
use Illuminate\Support\Facades\Mail;

use function PHPUnit\Framework\returnSelf;

class Helper
{
    public static function exchange($amount, $currency)
    {
        $rate = self::rate($currency);

        if ($rate == -1)
            return -1;
        else
            return $amount / $rate;
    }

    private static function rate($currency)
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        $client = new \GuzzleHttp\Client([
            'headers' => $headers,
            'verify' => false
        ]);

        $r = $client->request('get', "https://developers.paysera.com/tasks/api/currency-exchange-rates");

        $response = $r->getBody()->getContents();
        $statusCode = $r->getStatusCode();

        if ($statusCode == 200) {
            $responseData = json_decode($response);
            if (isset($responseData->rates->$currency))
                return $responseData->rates->$currency;
            else
                return -1;
        } else
            return -1;
    }

    public static function withdrawLimit($currency)
    {
        $rate = self::rate($currency);

        return Limits::WITHDRAW_LIMIT * $rate;
    }

    public static function roundUp($number, $decimal = 2)
    {
        return number_format($number, $decimal);
    }
}
