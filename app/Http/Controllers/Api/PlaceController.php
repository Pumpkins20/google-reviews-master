<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Place;
use Illuminate\Support\Facades\Validator;

class PlaceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $places = Place::withCount('reviews')->get();
        return response()->json([
            'status' => 'success',
            'data' => $places
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'place_id' => 'required|string|unique:places,place_id',
            'store_code' => 'nullable|string|unique:places,store_code',
            'place_name' => 'required|string',
            'original_url' => 'required|string',
            'resolved_url' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $place = Place::create(array_merge($request->all(), [
            'first_seen' => now()->toIso8601String(),
        ]));

        return response()->json([
            'status' => 'success',
            'data' => $place
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $place = Place::with([
            'reviews' => function($query) {
                // Sort reviews from newest to oldest
                $query->orderBy('review_date', 'desc');
            },
            'reviews.analysis'
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $place
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $place = Place::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'store_code' => 'nullable|string|unique:places,store_code,' . $place->place_id . ',place_id',
            'place_name' => 'sometimes|required|string',
            'original_url' => 'sometimes|required|string',
            'resolved_url' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $place->update($request->all());

        return response()->json([
            'status' => 'success',
            'data' => $place
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $place = Place::findOrFail($id);
        $place->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Place deleted successfully'
        ]);
    }
}
