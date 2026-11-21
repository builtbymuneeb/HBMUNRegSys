<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Models\Delegate;
use App\Support\CommitteeExport;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommitteeExportController extends Controller
{
    /**
     * Export the allocated delegates of one or more committees (?committees[]=1, none means all)
     * with the picked columns (?columns[]=name), as CSV (?format=csv) or a print-ready page to save as PDF (?format=pdf).
     */
    public function __invoke(Request $request): StreamedResponse|View
    {
        $ids = array_filter(array_map('intval', (array) $request->query('committees', [])));
        $columns = CommitteeExport::pick($request->query('columns'));
        $labels = array_map(fn (string $key) => CommitteeExport::columns()[$key], $columns);

        $committees = Committee::ordered()
            ->when($ids, fn ($query) => $query->whereKey($ids))
            ->with(['allocatedDelegates' => fn ($query) => $query
                ->with(['registration', 'firstCommittee', 'secondCommittee'])
                ->orderBy('allocated_portfolio')
                ->orderBy('name')])
            ->get();

        $scope = $committees->count() === 1 ? $committees->first()->code : 'committees';
        $filename = 'hbmun-'.Str::slug($scope).'-delegates-'.now()->format('Y-m-d');

        if ($request->query('format') === 'pdf') {
            return view('committee-export', [
                'committees' => $committees,
                'columns' => $columns,
                'labels' => $labels,
                'title' => $filename,
                'heading' => $committees->count() === 1 ? $committees->first()->label() : __('Committee delegates'),
            ]);
        }

        return $this->csv($committees, $columns, $labels, $filename.'.csv');
    }

    /**
     * @param  Collection<int, Committee>  $committees
     * @param  list<string>  $columns
     * @param  list<string>  $labels
     */
    private function csv(Collection $committees, array $columns, array $labels, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($committees, $columns, $labels) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                throw new RuntimeException('Could not open the output stream for the CSV export.');
            }

            // Byte order mark so Excel opens the file as UTF-8.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $labels);

            foreach ($committees as $committee) {
                $committee->allocatedDelegates->each(fn (Delegate $delegate) => fputcsv($out, array_map(
                    fn (string $column) => $this->safeCell(CommitteeExport::value($column, $delegate, $committee)),
                    $columns,
                )));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Stop spreadsheet apps from running values typed into the form as formulas.
     */
    private function safeCell(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
