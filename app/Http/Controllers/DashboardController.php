<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Place;
use App\Models\Review;
use App\Models\ReviewAnalysis;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display the global dashboard overview.
     */
    public function index()
    {
        $defaultProvider = config('ai.default', 'gemini');
        $totalStores = Place::count();
        $totalReviews = Review::count();
        $totalSpam = ReviewAnalysis::where('provider', $defaultProvider)->where('spam_label', 'spam')->count();
        $avgRating = Review::avg('rating') ?? 0;

        // Calculate category distribution counts
        $categories = ['POSITIF', 'NEGATIF', 'CAMPURAN', 'SPAM_PROMOSI', 'INDIKASI_PENIPUAN', 'PERTANYAAN'];
        $categoryCounts = [];
        foreach ($categories as $cat) {
            $categoryCounts[$cat] = ReviewAnalysis::where('provider', $defaultProvider)->where('category', $cat)->count();
        }

        // Get places list with review count and average rating
        $places = Place::withCount('reviews')->get()->map(function ($place) use ($defaultProvider) {
            $place->avg_rating = DB::table('reviews')
                ->where('place_id', $place->place_id)
                ->avg('rating') ?? 0;

            $place->spam_count = DB::table('review_analysis')
                ->where('place_id', $place->place_id)
                ->where('provider', $defaultProvider)
                ->where('spam_label', 'spam')
                ->count();

            return $place;
        });

        return view('dashboard.index', compact(
            'totalStores',
            'totalReviews',
            'totalSpam',
            'avgRating',
            'categoryCounts',
            'places'
        ));
    }

    /**
     * Display details and reviews for a single store.
     */
    public function show(Request $request, $id)
    {
        $place = Place::findOrFail($id);
        $defaultProvider = config('ai.default', 'gemini');

        // Store stats
        $totalReviews = Review::where('place_id', $id)->count();
        $avgRating = Review::where('place_id', $id)->avg('rating') ?? 0;
        $spamCount = ReviewAnalysis::where('place_id', $id)->where('provider', $defaultProvider)->where('spam_label', 'spam')->count();

        // Build filtered reviews query
        $query = Review::with('analysis')->where('place_id', $id);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('author', 'like', "%{$search}%")
                  ->orWhere('review_text', 'like', "%{$search}%");
            });
        }

        if ($request->filled('rating')) {
            $query->where('rating', $request->input('rating'));
        }

        if ($request->filled('spam_label')) {
            $label = $request->input('spam_label');
            $query->whereHas('analysis', function ($q) use ($label) {
                $q->where('spam_label', $label);
            });
        }

        if ($request->filled('category')) {
            $cat = $request->input('category');
            $query->whereHas('analysis', function ($q) use ($cat) {
                $q->where('category', $cat);
            });
        }

        // Ordered by newest first
        $reviews = $query->orderBy('review_date', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.show', compact(
            'place',
            'reviews',
            'totalReviews',
            'avgRating',
            'spamCount'
        ));
    }
}
