<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,jpg,png|max:2048',
            'user_id' => 'required|exists:users,id',
        ]);

        $file = $request->file('file');
        $filePath = $file->store('documents', 'public');

        $document = Document::create([
            'user_id' => $request->user_id,
            'file_path' => $filePath,
        ]);

        return response()->json(['message' => 'Document uploaded successfully', 'document' => $document], 201);
    }

    // Search for documents by user social number
    public function search(Request $request)
    {
        $request->validate([
            'social_number' => 'required|exists:users,social_number',
        ]);

        $documents = Document::whereHas('user', function ($query) use ($request) {
            $query->where('social_number', $request->social_number);
        })->get();

        return response()->json(['documents' => $documents], 200);
    }

    // Download a document by ID
    public function download($id)
    {
        $document = Document::findOrFail($id);
        $filePath = $document->file_path;

        if (Storage::disk('public')->exists($filePath)) {
            return Storage::disk('public')->download($filePath);
        }

        return response()->json(['message' => 'File not found'], 404);
    }
}
