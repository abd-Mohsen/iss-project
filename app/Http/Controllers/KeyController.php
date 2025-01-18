<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KeyController extends Controller
{
    public function getServerPublicKey()
    {
        $publicKeyPath = storage_path('app/keys/server_public_key.pem');

        if (!file_exists($publicKeyPath)) {
            return response()->json(['error' => 'Public key not found'], 404);
        }

        $publicKey = file_get_contents($publicKeyPath);

        return response($publicKey, 200)
            ->header('Content-Type', 'text/plain');
    }
}

