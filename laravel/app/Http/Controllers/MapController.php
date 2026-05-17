<?php

namespace App\Http\Controllers;

use App\Models\ChangePoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapController extends Controller
{
    public function index()
    {
        $years = ChangePoint::distinct()->orderBy('year')->pluck('year');
        $counties = ChangePoint::distinct()->orderBy('county_city')->pluck('county_city');
        $changeTypes = ChangePoint::distinct()->orderBy('change_type')->pluck('change_type')->filter();
        $verificationResults = ChangePoint::distinct()->orderBy('verification_result')->pluck('verification_result')->filter();

        return view('map.index', compact('years', 'counties', 'changeTypes', 'verificationResults'));
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'radius' => 'required|numeric|min:1|max:50000',
        ]);

        $lat = (float) $request->lat;
        $lng = (float) $request->lng;
        $radius = (float) $request->radius;

        $query = ChangePoint::withinRadius($lat, $lng, $radius);

        if ($request->filled('year_from')) {
            $query->where('year', '>=', (int) $request->year_from);
        }
        if ($request->filled('year_to')) {
            $query->where('year', '<=', (int) $request->year_to);
        }
        if ($request->filled('county_city')) {
            $query->where('county_city', $request->county_city);
        }
        if ($request->filled('change_type')) {
            $changeTypes = (array) $request->input('change_type');
            $query->whereIn('change_type', $changeTypes);
        }
        if ($request->filled('verification_result')) {
            $query->where('verification_result', $request->verification_result);
        }

        $results = $query->orderBy('distance')->limit(1000)->get();

        return response()->json([
            'count' => $results->count(),
            'results' => $results->map(fn ($p) => [
                'id' => $p->id,
                'point_id' => $p->point_id,
                'authority' => $p->authority,
                'county_city' => $p->county_city,
                'verification_result' => $p->verification_result,
                'change_type' => $p->change_type,
                'year' => $p->year,
                'latitude' => (float) $p->latitude,
                'longitude' => (float) $p->longitude,
                'distance' => round((float) $p->distance, 1),
            ]),
        ]);
    }
}
