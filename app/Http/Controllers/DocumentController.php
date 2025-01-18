<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Document;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\RSA;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    private $privateKeyPath = 'keys/server_private_key.pem';
    private $publicKeyPath = 'keys/server_public_key.pem';
    private $clientPublicKeyPath = 'keys/client_public_key.pem';
    private $clientPrivateKeyPath = 'keys/client_private_key.pem';


    private function encryptFile($fileContents)
    {
        // Load the client's public key (not the server's public key)
        $publicKey = RSA::loadPublicKey(Storage::get($this->clientPublicKeyPath));

        // Generate a random 256-bit AES key
        $aesKey = random_bytes(32);

        // Generate a random Initialization Vector (IV)
        $iv = random_bytes(16);

        // Set up AES encryption in CBC mode
        $aes = new AES('cbc');
        $aes->setKey($aesKey);
        $aes->setIV($iv);

        // Encrypt the file contents using AES
        $encryptedData = $aes->encrypt($fileContents);

        // Encrypt the AES key with the client's public key
        $encryptedAesKey = $publicKey->encrypt($aesKey);

        // Return the encrypted data, AES key, and IV
        return [
            'encrypted_data' => base64_encode($encryptedData),
            'encrypted_aes_key' => base64_encode($encryptedAesKey),
            'iv' => base64_encode($iv),
        ];
    }


    // Decrypt file using hybrid decryption
    private function decryptFile($encryptedData, $encryptedAesKey, $iv)
    {
        $privateKey = RSA::loadPrivateKey(Storage::get($this->privateKeyPath));

        // Decrypt the AES key
        $aesKey = $privateKey->decrypt(base64_decode($encryptedAesKey));

        // Decrypt the file contents using AES
        $aes = new AES('cbc');
        $aes->setKey($aesKey);
        $aes->setIV(base64_decode($iv));
        return $aes->decrypt(base64_decode($encryptedData));
    }

    public function upload(Request $request)
    {

        info('print', ["kll"]);
        $data = $request->validate([
            'encrypted_data' => 'required',
            'encrypted_aes_key' => 'required',
            'iv' => 'required',
        ]);


        info('print', $data);

        //Log::emergency('An informational message.');
    
    
        info('print', $data);

        //Log::emergency('An informational message.');
    
        // Decrypt the file
        $decryptedFileContents = $this->decryptFile(
            $request->input('encrypted_data'),
            $request->input('encrypted_aes_key'),
            $request->input('iv')
        );

        // info('print', ["dycrypted"]);

        // Save the file locally
        $fileExtension = $request->input('file_extension', 'bin'); // Default to 'bin' if not provided
        $fileName = 'decrypted_' . time() . '.' . $fileExtension;
        $filePath = "documents/{$fileName}";
        Storage::put($filePath, $decryptedFileContents);
    
        // Calculate file hash
        $fileHash = hash('sha256', $decryptedFileContents);
    
        // Step 1: Request signature from CA
        $signatureResponse = Http::attach(
            'file', $decryptedFileContents, $fileName
        )->post('http://127.0.0.1:5000/sign_doc');
    
        if (!$signatureResponse->ok()) {
            return response()->json(['error' => 'Failed to get the signature from CA'], 500);
        }
    
        $signature = $signatureResponse->json()['signature'];
    
        // Step 2: Store file info and signature in the database
        $document = Document::create([
            'user_id' => $request->user_id,
            'file_path' => $filePath,
            'file_hash' => $fileHash,
            'signature' => $signature, // Store the signature
        ]);
        //info('print', ["saved"]);
    
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
                    'created_at' => $doc->created_at->timezone('Asia/Riyadh')->format('d M Y, h:i A'),
                    'file_path' => $doc->file_path,
                ];
            }),
        ], 200);
    }
    

    public function download($id)
    {
        $document = Document::findOrFail($id);
    
        // Recalculate the file's hash
        $filePath = $document->file_path;
        $fileContents = Storage::get($filePath);
        $calculatedHash = hash('sha256', $fileContents);
    
        if ($calculatedHash !== $document->file_hash) {
            return response()->json(['message' => 'File integrity check failed.'], 403);
        }
    
        // Extract file extension
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
    
        // Encrypt the file using hybrid encryption
        $encryptedFile = $this->encryptFile($fileContents);
    
        return response()->json([
            'encrypted_data' => $encryptedFile['encrypted_data'],
            'encrypted_aes_key' => $encryptedFile['encrypted_aes_key'],
            'iv' => $encryptedFile['iv'],
            'file_extension' => $fileExtension, // Add file extension to the response
            'signature' => $document->signature,
        ]);
    }
    
    
    
}
