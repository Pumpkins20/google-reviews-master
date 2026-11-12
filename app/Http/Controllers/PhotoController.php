<?php

namespace App\Http\Controllers;

use App\Models\PhotoAnalysis;
use App\Models\PhotoReport;
use App\Models\PlacePhoto;
use App\Models\Store;
use App\Services\Ai\PhotoClassifier;
use Illuminate\Http\Request;

class PhotoController extends Controller
{
    const FILTERS = [
        'flagged' => 'Ditandai spam/scam',
        'pending' => 'Perlu dilaporkan',
        'reported' => 'Sudah dilaporkan',
        'ok' => 'Aman',
        'unanalyzed' => 'Belum dianalisis',
        'all' => 'Semua',
    ];

    /**
     * Photo list with filters and the cost summary of the whole feature.
     */
    public function index(Request $request)
    {
        $filter = array_key_exists($request->query('status'), self::FILTERS) ? $request->query('status') : 'flagged';
        $q = trim((string) $request->query('q'));

        $photos = PlacePhoto::active()
            ->with(['place', 'latestAnalysis', 'report'])
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('place', function ($p) use ($q) {
                    $p->where('place_name', 'like', "%{$q}%")->orWhere('store_code', 'like', "%{$q}%");
                });
            });

        switch ($filter) {
            case 'flagged':
                $photos->flagged();
                break;
            case 'pending':
                $photos->flagged()->where(function ($w) {
                    $w->whereDoesntHave('report')->orWhereHas('report', function ($r) {
                        $r->where('status', 'pending');
                    });
                });
                break;
            case 'reported':
                $photos->whereHas('report', function ($r) {
                    $r->where('status', 'reported');
                });
                break;
            case 'ok':
                $photos->where('label', 'ok');
                break;
            case 'unanalyzed':
                $photos->whereNull('analyzed_at');
                break;
        }

        $photos = $photos->orderByDesc('analyzed_at')->orderByDesc('id')->paginate(24)->appends(['status' => $filter, 'q' => $q]);

        return view('photos.index', [
            'photos' => $photos,
            'filter' => $filter,
            'filters' => self::FILTERS,
            'q' => $q,
            'stats' => $this->stats(),
        ]);
    }

    /**
     * Photo detail. A flagged photo goes straight to its report page.
     */
    public function show($id)
    {
        $photo = $this->find($id);
        $report = $photo->report;

        if ($photo->isFlagged() && (!$report || $report->status !== 'dismissed')) {
            return redirect()->route('photos.report', $photo->id);
        }

        return view('photos.show', $this->viewData($photo, false));
    }

    /**
     * Report page: evidence, link to the photo on Google Maps, and report status.
     */
    public function report($id)
    {
        return view('photos.show', $this->viewData($this->find($id), true));
    }

    /**
     * Send the user straight to Google's report page for this photo.
     */
    public function reportRedirect($id)
    {
        $photo = $this->find($id);
        $url = $photo->reportUrl();

        if (!$url) {
            return redirect()->route('photos.report', $photo->id)
                ->with('error', 'Tautan laporan belum bisa dibuat: link halaman foto atau ID tempat lengkap belum tersimpan. Jalankan scrape ulang untuk toko ini.');
        }

        return redirect()->away($url);
    }

    /**
     * Save the report status (Google offers no API; the report itself is made on Maps).
     */
    public function updateReport(Request $request, $id)
    {
        $photo = $this->find($id);
        $data = $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(PhotoReport::STATUSES)),
            'note' => 'nullable|string|max:1000',
        ]);

        PhotoReport::updateOrCreate(['photo_id' => $photo->id], [
            'status' => $data['status'],
            'note' => $data['note'] ?? null,
            'reported_at' => $data['status'] === 'reported' ? now() : null,
        ]);

        return redirect()->route('photos.report', $photo->id)->with('success', 'Status laporan disimpan.');
    }

    /**
     * Analyse (or re-analyse) one photo right away; one Gemini call takes a few seconds.
     */
    public function analyze($id, PhotoClassifier $classifier)
    {
        $photo = $this->find($id);

        try {
            $analysis = $classifier->classify($photo);
        } catch (\Exception $e) {
            return back()->with('error', 'Analisis gagal: ' . $e->getMessage());
        }

        return redirect()->route('photos.show', $photo->id)
            ->with('success', 'Foto dianalisis: ' . $analysis->label . ' (biaya $' . number_format((float) $analysis->cost, 6) . ').');
    }

    protected function find($id)
    {
        return PlacePhoto::with(['place', 'latestAnalysis', 'report', 'analyses'])->findOrFail($id);
    }

    protected function viewData(PlacePhoto $photo, $reportMode)
    {
        $code = $photo->place ? $photo->place->store_code : null;

        return [
            'photo' => $photo,
            'analysis' => $photo->latestAnalysis,
            'store' => $code ? Store::safe()->where('kode_toko', $code)->first() : null,
            'report' => $photo->report,
            'reportMode' => $reportMode,
        ];
    }

    /**
     * Counts and Gemini cost of the whole feature.
     *
     * @return array
     */
    protected function stats()
    {
        $cost = PhotoAnalysis::selectRaw('COUNT(*) as runs, COALESCE(SUM(cost), 0) as total, COALESCE(AVG(cost), 0) as average, '
            . 'COALESCE(SUM(input_tokens), 0) as input_tokens, COALESCE(SUM(output_tokens), 0) as output_tokens')->first();

        return [
            'photos' => PlacePhoto::active()->count(),
            'analyzed' => PlacePhoto::active()->whereNotNull('analyzed_at')->count(),
            'flagged' => PlacePhoto::active()->flagged()->count(),
            'reported' => PhotoReport::where('status', 'reported')->count(),
            'runs' => (int) $cost->runs,
            'total_cost' => (float) $cost->total,
            'avg_cost' => (float) $cost->average,
            'month_cost' => (float) PhotoAnalysis::where('analyzed_at', '>=', now()->startOfMonth())->sum('cost'),
            'input_tokens' => (int) $cost->input_tokens,
            'output_tokens' => (int) $cost->output_tokens,
            'model' => config('photo_ai.model'),
        ];
    }
}
