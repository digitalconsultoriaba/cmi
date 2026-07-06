<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// Todos os eventos existentes passam a ter 1 dia (spec 012, FR-006/SC-001):
// cria 1 event_day (Dia 1) a partir da data principal do evento. Idempotente.
return new class extends Migration
{
    public function up(): void
    {
        $events = DB::table('events')
            ->whereNull('deleted_at')
            ->select('id', 'starts_at', 'ends_at')
            ->get();

        foreach ($events as $event) {
            $exists = DB::table('event_days')->where('event_id', $event->id)->exists();
            if ($exists) {
                continue;
            }

            $starts = $event->starts_at ? Carbon::parse($event->starts_at) : null;
            $ends = $event->ends_at ? Carbon::parse($event->ends_at) : null;

            DB::table('event_days')->insert([
                'event_id' => $event->id,
                'day_number' => 1,
                'event_date' => $starts ? $starts->toDateString() : Carbon::now()->toDateString(),
                'starts_at' => $starts ? $starts->format('H:i:s') : null,
                'ends_at' => $ends ? $ends->format('H:i:s') : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Backfill não é revertido individualmente (dias passam a ser autoritativos).
    }
};
