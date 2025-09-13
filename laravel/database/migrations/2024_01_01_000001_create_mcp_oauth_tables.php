<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Authorization codes table
        Schema::create('mcp_authorization_codes', function (Blueprint $table) {
            $table->string('code', 128)->primary();
            $table->text('data'); // JSON encoded authorization data
            $table->timestamp('expires_at');
            $table->timestamp('created_at');
            
            $table->index(['expires_at']);
        });

        // Access tokens table
        Schema::create('mcp_access_tokens', function (Blueprint $table) {
            $table->string('token', 128)->primary();
            $table->text('data'); // JSON encoded token data (client_id, user_id, scopes)
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->index(['expires_at']);
            $table->index(['created_at']);
        });

        // Refresh tokens table
        Schema::create('mcp_refresh_tokens', function (Blueprint $table) {
            $table->string('token', 128)->primary();
            $table->text('data'); // JSON encoded token data (client_id, user_id, scopes)
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->index(['expires_at']);
        });

        // Sessions table (if not using Laravel's default sessions)
        if (!Schema::hasTable('mcp_sessions')) {
            Schema::create('mcp_sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('session_id')->unique();
                $table->text('data'); // JSON encoded session data
                $table->string('transport_type', 50)->default('http'); // http, stdio, websocket
                $table->string('client_info', 255)->nullable(); // User agent, IP, etc.
                $table->timestamp('last_activity');
                $table->timestamps();
                
                $table->index(['last_activity']);
                $table->index(['transport_type']);
                $table->index(['created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mcp_sessions');
        Schema::dropIfExists('mcp_refresh_tokens');
        Schema::dropIfExists('mcp_access_tokens');
        Schema::dropIfExists('mcp_authorization_codes');
    }
};