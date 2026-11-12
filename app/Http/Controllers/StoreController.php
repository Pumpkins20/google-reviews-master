<?php

namespace App\Http\Controllers;

use App\Jobs\ClassifyPhotosJob;
use App\Jobs\ScrapeStoresJob;
use App\Models\Place;
use App\Models\PlacePhoto;
use App\Models\Store;
use App\Services\PhotoSyncService;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    /**
     * Store list (master data from the store table) with Google Maps stats.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $stores = Store::safe()
            ->whereNull('deleted_at')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('nama_toko', 'like', "%{$q}%")
                      ->orWhere('kode_toko', 'like', "%{$q}%")
                      ->orWhere('kota_kab', 'like', "%{$q}%");
                });
            })
            ->orderByRaw("(link_gmaps IS NULL OR link_gmaps = '') ASC")
            ->orderBy('nama_toko')
            ->paginate(25)
            ->appends(['q' => $q]);

        $places = Place::whereIn('store_code', $stores->pluck('kode_toko')->filter())
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->get()
            ->keyBy('store_code');

        return view('stores.index', compact('stores', 'places', 'q'));
    }

    /**
     * Queue a scrape (reviews + photos) for one store.
     */
    public function scrape($id)
    {
        $store = Store::safe()->whereNull('deleted_at')->findOrFail($id);

        if (!$store->link_gmaps || !$store->kode_toko) {
            return back()->with('error', 'Store ini belum punya link Google Maps atau kode toko.');
        }

        if (config('queue.default') === 'sync') {
            return back()->with('error', 'Queue masih "sync" sehingga scrape akan memblokir halaman. Set QUEUE_CONNECTION=database di .env dan jalankan "php artisan queue:work".');
        }

        ScrapeStoresJob::dispatch([$store->kode_toko], 1, 0);

        return back()->with('success', 'Scrape dijadwalkan. Hasil muncul di halaman ini setelah worker selesai (beberapa menit).');
    }

    /**
     * One store: master data plus the Google Maps photos gathered by the scraper.
     */
    public function show($id, PhotoSyncService $sync)
    {
        $store = Store::safe()->whereNull('deleted_at')->findOrFail($id);

        $place = $store->kode_toko
            ? Place::where('store_code', $store->kode_toko)
                ->withCount('reviews')
                ->withAvg('reviews', 'rating')
                ->first()
            : null;

        $photos = collect();
        if ($place) {
            $sync->syncPlace($place);
            $photos = PlacePhoto::active()->where('place_id', $place->place_id)->orderBy('id')->get();
        }

        return view('stores.show', compact('store', 'place', 'photos'));
    }

    /**
     * Queue Gemini classification of this store's photos that have no result yet.
     */
    public function analyzePhotos($id)
    {
        $store = Store::safe()->whereNull('deleted_at')->findOrFail($id);
        $place = $store->kode_toko ? Place::where('store_code', $store->kode_toko)->first() : null;

        if (!$place) {
            return back()->with('error', 'Store ini belum punya data Google Maps. Jalankan scrape dulu.');
        }
        if (!config('photo_ai.enabled')) {
            return back()->with('error', 'Klasifikasi foto dimatikan (PHOTO_AI_ENABLED=false).');
        }
        if (config('queue.default') === 'sync') {
            return back()->with('error', 'Queue masih "sync" sehingga analisis akan memblokir halaman. Set QUEUE_CONNECTION=database di .env dan jalankan "php artisan queue:work".');
        }

        ClassifyPhotosJob::dispatch([$place->place_id]);

        return back()->with('success', 'Analisis foto dijadwalkan. Hanya foto yang belum pernah dianalisis yang diproses (dan dikenai biaya).');
    }
}
