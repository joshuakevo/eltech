<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webauthn_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('credential_id', 512)->unique();   // base64url
            $table->text('public_key');                       // PEM
            $table->integer('alg');                           // COSE algorithm (-7 ES256, -257 RS256)
            $table->unsignedBigInteger('sign_count')->default(0);
            $table->string('name')->nullable();               // e.g. "Android phone"
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webauthn_credentials');
    }
};
