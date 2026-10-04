<?php

namespace App\Http\Controllers;

use App\Models\PatientRecord;
use App\Support\RecordType;
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

        // One count per known type, so a new type shows up on the dashboard
        // without touching this method.
        $counts = [];
        $prevCounts = [];
        foreach (RecordType::slugs() as $type) {
            $counts[$type] = $countRange($type, $from, $to);
        }

        // Equally sized window immediately before the selection, for deltas.
        $spanDays = (int) $from->diffInDays($to->copy()->startOfDay()) + 1;
        $prevFrom = $from->copy()->subDays($spanDays)->startOfDay();
        $prevTo = $from->copy()->subDay()->endOfDay();
        foreach (RecordType::slugs() as $type) {
            $prevCounts[$type] = $countRange($type, $prevFrom, $prevTo);
        }

        $total = array_sum($counts);
        $prevTotal = array_sum($prevCounts);

        $deltas = ['total' => $this->delta($total, $prevTotal)];
        foreach ($counts as $type => $count) {
            $deltas[$type] = $this->delta($count, $prevCounts[$type]);
        }

        $analytics = [
            'counts' => $counts,
            'error' => $counts[RecordType::ERROR],
            'success' => $counts[RecordType::SUCCESS],
            'mission' => $counts[RecordType::MISSION],
            'total' => $total,
            'is_today' => $from->isSameDay($today) && $to->isSameDay($today),
            'label' => $from->isSameDay($today) && $to->isSameDay($today)
                ? 'Today · '.$from->format('M j, Y')
                : $from->format('M j, Y').' – '.$to->format('M j, Y'),
            'error_rate' => $total > 0
                ? round($counts[RecordType::ERROR] / $total * 100)
                : 0,
            'delta' => $deltas,
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
        $slugs = RecordType::slugs();
        $buckets = [];
        foreach ($keys as $k) {
            $buckets[$k] = array_fill_keys(array_merge($slugs, ['total']), 0);
        }

        $expr = $byHour ? "strftime('%H', created_at)" : "date(created_at)";

        PatientRecord::whereBetween('created_at', [$from, $to])
            ->selectRaw("record_type, {$expr} as bucket, count(*) as aggregate")
            ->groupBy('record_type', 'bucket')
            ->get()
            ->each(function ($row) use (&$buckets, $slugs) {
                $key = (string) $row->bucket;
                if (!array_key_exists($key, $buckets)) {
                    return;
                }
                $n = (int) $row->aggregate;
                $buckets[$key]['total'] += $n;
                if (in_array($row->record_type, $slugs, true)) {
                    $buckets[$key][$row->record_type] += $n;
                }
            });

        $values = array_values($buckets);

        $series = [
            'by_hour' => $byHour,
            'total' => array_column($values, 'total'),
            'labels' => $labels,
            'axis' => $byHour ? 'Hour of day' : 'Day of period',
        ];
        foreach ($slugs as $type) {
            $series[$type] = array_column($values, $type);
        }

        return $series;
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