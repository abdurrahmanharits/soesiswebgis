<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSpatialDataset;
use App\Models\Dataset;
use Illuminate\Http\Request;

class DatasetController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Dataset::latest()->get(),
        ]);
    }

    public function show(Dataset $dataset)
    {
        return response()->json([
            // Provenance names for the internal detail page (null for legacy uploads).
            'data' => $dataset->load(['uploader:id,name', 'organization:id,name']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:524288'],
        ]);

        $file = $request->file('file');

        $extension = strtolower(
            $file->getClientOriginalExtension()
        );

        if (! in_array($extension, ['zip', 'tif', 'tiff'])) {
            return response()->json([
                'message' => 'Format file harus ZIP Shapefile atau GeoTIFF.',
            ], 422);
        }

        $format = $extension === 'zip'
            ? 'shapefile'
            : 'geotiff';

        $dataset = Dataset::create([
            'title' => $request->input('title'),
            'original_filename' => $file->getClientOriginalName(),
            'format' => $format,
            'status' => 'queued',
            'uploaded_by' => $request->user()->id,
            'organization_id' => $request->user()->organization_id,
        ]);

        $storedExtension = $extension === 'tiff'
            ? 'tif'
            : $extension;

        $path = $file->storeAs(
            "datasets/{$dataset->id}",
            "original.{$storedExtension}"
        );

        $dataset->update([
            'original_path' => $path,
        ]);

        ProcessSpatialDataset::dispatch($dataset->id)
            ->onQueue('spatial');

        return response()->json([
            'message' => 'Dataset berhasil diunggah dan masuk antrean pemrosesan.',
            'data' => $dataset->fresh(),
        ], 202);
    }
}