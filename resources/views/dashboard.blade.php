<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Home') }}
        </h2>
    </x-slot>

    <div class="p-6">
        @if(auth()->user()->role->title === 'user') <!-- Check if the role is 'User' -->
            <div>
                <!-- Upload Document Button -->
                <button 
                    id="uploadButton" 
                    class="bg-blue-500 text-white px-4 py-2 rounded"
                    onclick="document.getElementById('fileInput').click()">
                    Upload Document
                </button>
                
                <!-- Hidden File Input -->
                <input 
                    type="file" 
                    id="fileInput" 
                    class="hidden" 
                    accept=".pdf,.doc,.docx,.jpg,.png" 
                    onchange="uploadFile()">

                <!-- Loading Indicator -->
                <div id="loadingIndicator" class="hidden mt-4 text-blue-500">
                    Uploading...
                </div>

                <!-- Success Message -->
                <div id="successMessage" class="hidden mt-4 text-green-500">
                    File uploaded successfully!
                </div>
            </div>
        @elseif(auth()->user()->role->title === 'admin') <!-- Check if the role is 'Admin' -->
            <div class="mt-4">
                <!-- Search Section -->
                <label for="search_social_number" class="block text-sm font-medium text-gray-700">Search User's Documents by Social Number</label>
                <input 
                    type="text" 
                    id="search_social_number" 
                    name="search_social_number" 
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" 
                    placeholder="Enter Social Number">
                <button 
                    class="mt-2 bg-green-500 text-white px-4 py-2 rounded" 
                    onclick="searchDocuments()">
                    Search
                </button>

                <!-- Loading Indicator for Search -->
                <div id="searchLoadingIndicator" class="hidden mt-4 text-blue-500">
                    Searching...
                </div>

                <!-- Search Results -->
                <div id="searchResults" class="mt-4">
                    <!-- Results will be dynamically added here -->
                </div>
            </div>
        @endif
    </div>

    <script>

        async function uploadFile() {
            const fileInput = document.getElementById('fileInput');
            const loadingIndicator = document.getElementById('loadingIndicator');
            const successMessage = document.getElementById('successMessage');

            // Ensure a file is selected
            if (!fileInput.files.length) {
                alert('Please select a file to upload.');
                return;
            }

            // Show loading indicator and hide success message
            loadingIndicator.classList.remove('hidden');
            successMessage.classList.add('hidden');

            const file = fileInput.files[0];
            const fileContents = await file.arrayBuffer(); // Read the file contents as ArrayBuffer
            const fileExtension = file.name.split('.').pop();

            try {
                // Step 1: Fetch the server's public RSA key
                const publicKeyResponse = await fetch('/keys/server-public-key'); // Correct endpoint
                if (!publicKeyResponse.ok) {
                    throw new Error('Failed to fetch server public key');
                }
                const publicKeyPem = await publicKeyResponse.text();

                // Import the server's public key
                const publicKey = await window.crypto.subtle.importKey(
                    'spki',
                    convertPemToArrayBuffer(publicKeyPem),
                    { name: 'RSA-OAEP', hash: 'SHA-256' },
                    false,
                    ['encrypt']
                );

                // Step 2: Generate a random AES key and IV
                const aesKey = window.crypto.getRandomValues(new Uint8Array(32)); // 256-bit AES key
                const iv = window.crypto.getRandomValues(new Uint8Array(16)); // Initialization vector

                // Step 3: Encrypt the file using AES-CBC
                const aesCryptoKey = await window.crypto.subtle.importKey(
                    'raw',
                    aesKey.buffer,
                    { name: 'AES-CBC', length: 256 },
                    false,
                    ['encrypt']
                );
                const encryptedFile = await window.crypto.subtle.encrypt(
                    { name: 'AES-CBC', iv },
                    aesCryptoKey,
                    fileContents
                );

                // Step 4: Encrypt the AES key using the server's RSA public key
                const encryptedAesKey = await window.crypto.subtle.encrypt(
                    { name: 'RSA-OAEP' },
                    publicKey,
                    aesKey.buffer
                );

                // Step 5: Prepare the FormData with encrypted data, AES key, and IV
                const formData = new FormData();
                formData.append('encrypted_data', arrayBufferToBase64(encryptedFile));
                formData.append('encrypted_aes_key', arrayBufferToBase64(encryptedAesKey));
                formData.append('iv', arrayBufferToBase64(iv));
                formData.append('file_extension', fileExtension);
                formData.append('user_id', {{ auth()->id() }}); // Pass the authenticated user's ID

                // Step 6: Send the upload request to the server
                const response = await fetch("/documents/upload", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'accept': 'application/json'
                    },
                    body: formData
                });

                console.log(await response.text());

                // for (let [key, value] of formData.entries()) {
                //     console.log(`${key}: ${value}`);
                // }

                if (!response.ok) {
                    throw new Error('File upload failed');
                }

                // Hide loading indicator and show success message
                loadingIndicator.classList.add('hidden');
                successMessage.classList.remove('hidden');
            } catch (error) {
                // Handle errors
                loadingIndicator.classList.add('hidden');
                alert('An error occurred while uploading the file: ' + error.message);
                console.log('An error occurred while uploading the file: ' + error.message);
            } finally {
                // Reset the file input
                fileInput.value = '';
            }
        }

        // Utility function to convert PEM to ArrayBuffer
        function convertPemToArrayBuffer(pem) {
            const pemHeader = "-----BEGIN PUBLIC KEY-----";
            const pemFooter = "-----END PUBLIC KEY-----";
            const pemContents = pem.replace(pemHeader, "").replace(pemFooter, "").replace(/\s+/g, "");
            const binaryDerString = atob(pemContents);
            const binaryDer = new Uint8Array(binaryDerString.length);
            for (let i = 0; i < binaryDerString.length; i++) {
                binaryDer[i] = binaryDerString.charCodeAt(i);
            }
            return binaryDer.buffer;
        }

        // Utility function to convert ArrayBuffer to Base64
        function arrayBufferToBase64(buffer) {
            const binary = String.fromCharCode.apply(null, new Uint8Array(buffer));
            return btoa(binary);
        }


        // async function fetchServerPublicKey() {
        //     try {
        //         // Fetch the public key from the server
        //         const publicKeyResponse = await fetch('/keys/server-public-key');
                
        //         if (!publicKeyResponse.ok) {
        //             throw new Error('Failed to fetch server public key');
        //         }

        //         const publicKeyPem = await publicKeyResponse.text();
        //         console.log("Public Key PEM:", publicKeyPem);

        //         // Import the public key for use with WebCrypto
        //         const publicKey = await window.crypto.subtle.importKey(
        //             'spki', // Public key format
        //             convertPemToArrayBuffer(publicKeyPem), // Convert PEM to ArrayBuffer
        //             {
        //                 name: 'RSA-OAEP',
        //                 hash: 'SHA-256'
        //             },
        //             false, // Not extractable
        //             ['encrypt']
        //         );

        //         return publicKey;
        //     } catch (error) {
        //         console.error('Error fetching or importing public key:', error.message);
        //         throw error;
        //     }
        // }

        // Utility to convert PEM to ArrayBuffer
        // function convertPemToArrayBuffer(pem) {
        //     // Remove PEM header/footer and line breaks
        //     const pemHeader = "-----BEGIN PUBLIC KEY-----";
        //     const pemFooter = "-----END PUBLIC KEY-----";
        //     const pemContents = pem.replace(pemHeader, "").replace(pemFooter, "").replace(/\s+/g, "");
        //     const binaryDerString = atob(pemContents); // Decode Base64
        //     const binaryDer = new Uint8Array(binaryDerString.length);

        //     for (let i = 0; i < binaryDerString.length; i++) {
        //         binaryDer[i] = binaryDerString.charCodeAt(i);
        //     }

        //     return binaryDer.buffer;
        // }


        // Helper function to import the server's public key
        async function importServerPublicKey(pemKey) {
            const pemContents = pemKey
                .replace(/-----BEGIN PUBLIC KEY-----/, '')
                .replace(/-----END PUBLIC KEY-----/, '')
                .replace(/\s/g, '');
            const binaryDerString = atob(pemContents);
            const binaryDer = Uint8Array.from(binaryDerString, char => char.charCodeAt(0));
            return await window.crypto.subtle.importKey(
                'spki',
                binaryDer.buffer,
                { name: 'RSA-OAEP', hash: 'SHA-256' },
                false,
                ['encrypt']
            );
        }

        // Helper function to convert ArrayBuffer to Base64
        // function arrayBufferToBase64(buffer) {
        //     const binary = String.fromCharCode(...new Uint8Array(buffer));
        //     return btoa(binary);
        // }


        async function searchDocuments() {
            const searchInput = document.getElementById('search_social_number');
            const searchLoadingIndicator = document.getElementById('searchLoadingIndicator');
            const searchResults = document.getElementById('searchResults');

            const socialNumber = searchInput.value.trim();

            if (!socialNumber) {
                alert('Please enter a social number to search.');
                return;
            }

            // Show loading indicator and clear previous results
            searchLoadingIndicator.classList.remove('hidden');
            searchResults.innerHTML = '';

            try {
                // Send the search request to the server
                const response = await fetch(`{{ route('documents.search') }}?social_number=${socialNumber}`, {
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'An unexpected error occurred.');
                }

                const data = await response.json();

                if (data.documents && data.documents.length > 0) {
                    // Display the documents
                    const documentsHtml = data.documents.map(doc => `
                        <div class="mt-2 p-4 border border-gray-300 rounded-md bg-gray-800 text-white">
                            <p>Document ID: ${doc.id}</p>
                            <p>Uploaded At: ${doc.created_at}</p>
                            <a 
                                href="/documents/download/${doc.id}" 
                                class="text-blue-400 underline">
                                Download
                            </a>
                        </div>
                    `).join('');
                    searchResults.innerHTML = documentsHtml;
                } else {
                    // Display "No documents found"
                    searchResults.innerHTML = `<p class="mt-2 text-red-500">No documents found</p>`;
                }
            } catch (error) {
                // Display error message
                searchResults.innerHTML = `<p class="mt-2 text-red-500">${error.message}</p>`;
            } finally {
                // Hide loading indicator
                searchLoadingIndicator.classList.add('hidden');
            }
        }

    </script>
</x-app-layout>
