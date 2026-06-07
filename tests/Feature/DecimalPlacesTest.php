<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DecimalPlacesTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_setting_get_returns_default_when_not_set(): void
    {
        $value = SystemSetting::get('decimal_places', 2);
        $this->assertEquals(2, $value);
    }

    public function test_system_setting_get_returns_stored_value(): void
    {
        SystemSetting::updateOrCreate(
            ['key' => 'decimal_places'],
            ['value' => '0', 'group' => 'general', 'label' => 'Decimal Places', 'type' => 'number']
        );

        \Cache::forget('setting_decimal_places');

        $value = SystemSetting::get('decimal_places', 2);
        $this->assertEquals('0', $value);
        $this->assertEquals(0, (int) $value);
    }

    public function test_system_setting_set_updates_value(): void
    {
        SystemSetting::updateOrCreate(
            ['key' => 'decimal_places'],
            ['value' => '2', 'group' => 'general', 'label' => 'DP', 'type' => 'number']
        );

        SystemSetting::set('decimal_places', '0');

        \Cache::forget('setting_decimal_places');
        $this->assertEquals('0', SystemSetting::get('decimal_places', 2));
    }
}
