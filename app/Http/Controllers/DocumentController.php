<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,txt|max:2048',
            //'user_id' => 'required|exists:users,id',
        ]);

        $file = $request->file('file');
        $filePath = $file->store('documents', 'local');

        $fileContents = file_get_contents($file->getRealPath());
        $fileHash = hash('sha256', $fileContents);

        $document = Document::create([
            'user_id' => $request->user_id,
            'file_path' => $filePath,
            'file_hash' => $fileHash,
        ]);

        return response()->json(['message' => 'Document uploaded successfully', 'document' => $document], 201);
    }

    public function search(Request $request)
    {
        $socialNumber = $request->query('social_number');
    
        // Validate the input
        if ($socialNumber == null) {
            return response()->json([
                'message' => 'Social number is required.',
            ], 400);
        }
    
        // Find the user by social number
        $user = User::where('social_number', $socialNumber)->first();
    
        if (!$user) {
            return response()->json([
                'message' => 'No user found with the given social number.',
            ], 404);
        }
    
        // Retrieve the user's documents
        $documents = Document::where('user_id', $user->id)->get();
    
        // Return documents in JSON format
        return response()->json([
            'message' => 'Documents retrieved successfully.',
            'documents' => $documents->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'created_at' => $doc->created_at->timezone('Asia/Riyadh')->format('d M Y, h:i A'), // Format for Arabian Standard Time
                    'file_path' => $doc->file_path,
                ];
            }),
        ], 200);
    }
    

    // Download a document by ID
    public function download($id)
    {
        // $document = Document::findOrFail($id);
        // $filePath = $document->file_path;

        // if (Storage::disk('public')->exists($filePath)) {
        //     return Storage::disk('public')->download($filePath);
        // }

        // return response()->json(['message' => 'File not found'], 404);


        $document = Document::findOrFail($id);

        // Recalculate the file's hash
        $filePath = $document->file_path;
        $fileContents = Storage::get($filePath);
        $calculatedHash = hash('sha256', $fileContents);
    
        // Compare the calculated hash with the stored hash
        if ($calculatedHash !== $document->file_hash) {
            return response()->json(['message' => 'File integrity check failed. The file may have been tampered with.'], 403);
        }
    
        // Serve the file for download
        return Storage::download($filePath);
    }
}
