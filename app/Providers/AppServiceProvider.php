<?php

namespace App\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $certificatePath = 'keys/server.crt';
        $keyPath = 'keys/server.key';
    
        // Check if the certificates exist, and fetch from CA if not
        
            // Check if the certificate and key exist in the storage folder
        if (!Storage::exists($certificatePath) || !Storage::exists($keyPath)) {
            // Fetch certificates from the CA server
            $response = Http::post('http://localhost:5000/generate_certificate');
            
            if ($response->successful()) {
                // Get certificate and private key from the response
                $certificate = $response->json()['certificate'];
                $privateKey = $response->json()['private_key'];

                // Store the certificate and private key in storage
                Storage::put($certificatePath, $certificate);
                Storage::put($keyPath, $privateKey);
                
                info("Certificate and private key saved to storage.");
            } else {
                error_log("Failed to fetch certificates from the CA server.");
            }
        }
    }
}
