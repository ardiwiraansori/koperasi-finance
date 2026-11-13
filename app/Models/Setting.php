<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Pengaturan koperasi (kunci -> nilai teks). Dibaca sekali per permintaan.
class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    private static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        self::$cache ??= static::query()->pluck('value', 'key')->all();
        return self::$cache[$key] ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        self::$cache = null;
    }

    public static function forget(): void { self::$cache = null; }

    // Koperasi punya akuntan? Bawaan: tidak, supaya kasir dan pengurus bisa bekerja tanpa istilah akuntansi.
    public static function hasAccountant(): bool { return self::get('has_accountant', '0') === '1'; }

    public static function cooperativeName(): string { return self::get('koperasi_name') ?: 'Koperasi Maju Bersama'; }
}
