<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_name',
        'business_email',
        'business_phone',
        'business_address',
        'default_reorder_level',
        'default_per_page',
        'date_format',
        'currency_symbol',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_reorder_level' => 'integer',
            'default_per_page' => 'integer',
        ];
    }

    /**
     * @return array{
     *     business_name: string,
     *     business_email: null,
     *     business_phone: null,
     *     business_address: null,
     *     default_reorder_level: int,
     *     default_per_page: int,
     *     date_format: string,
     *     currency_symbol: string
     * }
     */
    public static function defaults(): array
    {
        return [
            'business_name' => 'StockPilot',
            'business_email' => null,
            'business_phone' => null,
            'business_address' => null,
            'default_reorder_level' => 0,
            'default_per_page' => 10,
            'date_format' => 'Y-m-d',
            'currency_symbol' => '$',
        ];
    }

    /**
     * @return list<int>
     */
    public static function allowedPerPageOptions(): array
    {
        return [10, 15, 20, 25, 50];
    }

    /**
     * @return array<string, string>
     */
    public static function allowedDateFormats(): array
    {
        return [
            'Y-m-d' => 'YYYY-MM-DD',
            'd/m/Y' => 'DD/MM/YYYY',
            'm/d/Y' => 'MM/DD/YYYY',
            'd M Y' => 'DD Mon YYYY',
        ];
    }
}
