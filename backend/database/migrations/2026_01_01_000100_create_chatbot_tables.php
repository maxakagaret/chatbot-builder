<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('visits')->default(0);
            $table->json('tags')->nullable();
            $table->boolean('opted_out')->default(false);
            $table->boolean('is_test')->default(false);
            $table->timestamps();
        });

        // The same person can write from several channels; each channel knows them
        // by its own id (Telegram chat id, phone number, widget visitor key).
        Schema::create('contact_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('external_id');
            $table->unique(['channel', 'external_id']);
        });

        Schema::create('chatbots', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            // Work in progress: what the editor shows and the playground runs.
            $table->json('draft_graph');
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->timestamps();
        });

        // Immutable snapshots. Live sessions are pinned to the version they
        // started on, so publishing never breaks a conversation mid-way.
        Schema::create('chatbot_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chatbot_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('graph');
            $table->timestamp('published_at');
            $table->unique(['chatbot_id', 'version']);
        });

        // Which bot answers in which channel (one bot per channel).
        Schema::create('channel_bindings', function (Blueprint $table) {
            $table->string('channel', 16)->primary();
            $table->foreignId('chatbot_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('conversation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chatbot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chatbot_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16);
            $table->boolean('is_test')->default(false);
            $table->string('status', 16);
            $table->string('current_node_id')->nullable();
            $table->json('variables')->nullable();
            $table->unsignedTinyInteger('retries')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('last_activity_at');
            $table->timestamp('ended_at')->nullable();
            $table->index(['contact_id', 'channel', 'status']);
        });

        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_session_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 3); // in | out
            $table->string('node_id')->nullable();
            $table->text('text');
            $table->json('buttons')->nullable();
            $table->timestamp('created_at');
        });

        // What the (fake) channel providers were asked to send, in their wire format.
        Schema::create('outbound_log', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 16);
            $table->string('external_id');
            $table->json('payload');
            $table->timestamp('created_at');
        });

        // Target of the "book appointment" action (stand-in for a booking system).
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service');
            $table->string('preferred_time')->nullable();
            $table->string('reference')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['bookings', 'outbound_log', 'conversation_messages', 'conversation_sessions', 'channel_bindings', 'chatbot_versions', 'chatbots', 'contact_identities', 'contacts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
