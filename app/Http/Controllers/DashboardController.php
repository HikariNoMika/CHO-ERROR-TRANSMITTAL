<?php

namespace App\Http\Controllers;

use App\Models\PatientRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $today = Carbon::today();

        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : $today->copy();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : $today->copy()->endOfDay();

        if ($to->lessThan($from)) {
            $from = $to->copy()->startOfDay();
        }

        $countRange = fn ($type, Carbon $a, Carbon $b) => PatientRecord::where('record_type', $type)
            ->whereBetween('created_at', [$a, $b])
            ->count();

        $errorCount = $countRange('error', $from, $to);
        $successCount = $countRange('success', $from, $to);

        // Equally sized window immediately before the selection, for deltas.
        $spanDays = (int) $from->diffInDays($to->copy()->startOfDay()) + 1;
        $prevFrom = $from->copy()->subDays($spanDays)->startOfDay();
        $prevTo = $from->copy()->subDay()->endOfDay();
        $prevError = $countRange('error', $prevFrom, $prevTo);
        $prevSuccess = $countRange('success', $prevFrom, $prevTo);

        $analytics = [
            'error' => $errorCount,
            'success' => $successCount,
            'total' => $errorCount + $successCount,
            'is_today' => $from->isSameDay($today) && $to->isSameDay($today),
            'label' => $from->isSameDay($today) && $to->isSameDay($today)
                ? 'Today · '.$from->format('M j, Y')
                : $from->format('M j, Y').' – '.$to->format('M j, Y'),
            'error_rate' => ($errorCount + $successCount) > 0
                ? round($errorCount / ($errorCount + $successCount) * 100)
                : 0,
            'delta' => [
                'error' => $this->delta($errorCount, $prevError),
                'success' => $this->delta($successCount, $prevSuccess),
                'total' => $this->delta($errorCount + $successCount, $prevError + $prevSuccess),
            ],
            'prev_label' => $prevFrom->format('M j').' – '.$prevTo->format('M j, Y'),
            'series' => $this->series($from, $to),
        ];

        $recentRecords = PatientRecord::with(['template', 'creator', 'documentGenerations'])
            ->latest('created_at')
            ->paginate(8)
            ->withQueryString();

        return view('dashboard', compact('analytics', 'recentRecords') + [
            'from' => $from,
            'to' => $to,
            'today' => $today,
        ]);
    }

    /**
     * Bucketed counts for the sparklines: hourly for a single-day range,
     * daily otherwise. Empty buckets are kept so the shape stays honest.
     */
    private function series(Carbon $from, Carbon $to): array
    {
        $byHour = $from->isSameDay($to);

        $days = $byHour ? collect() : $this->dayRange($from, $to);
        $keys = $byHour
            ? collect(range(0, 23))->map(fn ($h) => sprintf('%02d', $h))
            : $days->map(fn (Carbon $d) => $d->format('Y-m-d'));
        $labels = $byHour
            ? collect(range(0, 23))->map(fn ($h) => sprintf('%02d:00', $h))->all()
            : $days->map(fn (Carbon $d) => $d->format('M j'))->all();

        // Plain array, not a Collection: buckets are mutated in place below,
        // which Collection's array access cannot do.
        $buckets = [];
        foreach ($keys as $k) {
            $buckets[$k] = ['error' => 0, 'success' => 0, 'total' => 0];
        }

        $expr = $byHour ? "strftime('%H', created_at)" : "date(created_at)";

        PatientRecord::whereBetween('created_at', [$from, $to])
            ->selectRaw("record_type, {$expr} as bucket, count(*) as aggregate")
            ->groupBy('record_type', 'bucket')
            ->get()
            ->each(function ($row) use (&$buckets) {
                $key = (string) $row->bucket;
                if (!array_key_exists($key, $buckets)) {
                    return;
                }
                $n = (int) $row->aggregate;
                $buckets[$key]['total'] += $n;
                if (in_array($row->record_type, ['error', 'success'], true)) {
                    $buckets[$key][$row->record_type] += $n;
                }
            });

        $values = array_values($buckets);

        return [
            'by_hour' => $byHour,
            'error' => array_column($values, 'error'),
            'success' => array_column($values, 'success'),
            'total' => array_column($values, 'total'),
            'labels' => $labels,
            'axis' => $byHour ? 'Hour of day' : 'Day of period',
        ];
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function dayRange(Carbon $from, Carbon $to): Collection
    {
        $days = collect();
        $cursor = $from->copy()->startOfDay();
        $last = $to->copy()->startOfDay();
        while ($cursor->lessThanOrEqualTo($last) && $days->count() < 366) {
            $days->push($cursor->copy());
            $cursor->addDay();
        }

        return $days;
    }

    /**
     * @return array{dir: string, pct: int|null, text: string}
     */
    private function delta(int $current, int $previous): array
    {
        if ($previous === 0) {
            return [
                'dir' => $current === 0 ? 'flat' : 'up',
                'pct' => null,
                'text' => $current === 0 ? 'No change' : 'New',
            ];
        }

        $pct = (int) round((($current - $previous) / $previous) * 100);

        return [
            'dir' => $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat'),
            'pct' => $pct,
            'text' => ($pct > 0 ? '+' : '').$pct.'%',
        ];
    }
}