<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Portal accounts invited before invites renamed leftover accounts still show an earlier owner's name
 * (e.g. avinyiak@gmail.com showing "Anne Avinyia" while it belongs to Dorah C. Mafabi). Give each
 * portal-only account the name of the member on its email and clear stray group roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        User::whereHas('portalClients')->get()->each->syncPortalIdentity();
    }

    public function down(): void
    {
        // Names are not restored
    }
};
