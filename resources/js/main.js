import * as base64js from 'base64-js';

async function uploadFile() {
    const fileInput = document.getElementById('fileInput');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const successMessage = document.getElementById('successMessage');

    if (!fileInput.files.length) {
        alert('Please select a file to upload.');
        return;
    }

    loadingIndicator.classList.remove('hidden');
    successMessage.classList.add('hidden');

    const file = fileInput.files[0];
    const fileExtension = file.name.split('.').pop();
    const chunkSize = 64 * 1024; // 64KB chunks to prevent memory issues
    const fileReader = new FileReader();

    try {
        // Step 1: Fetch the server's public RSA key
        const publicKeyResponse = await fetch('/keys/server-public-key');
        if (!publicKeyResponse.ok) throw new Error('Failed to fetch server public key');
        const publicKeyPem = await publicKeyResponse.text();

        const publicKey = await window.crypto.subtle.importKey(
            'spki',
            convertPemToArrayBuffer(publicKeyPem),
            { name: 'RSA-OAEP', hash: 'SHA-256' },
            false,
            ['encrypt']
        );

        // Step 2: Generate AES key and IV
        const aesKey = window.crypto.getRandomValues(new Uint8Array(32)); // 256-bit AES key
        const iv = window.crypto.getRandomValues(new Uint8Array(16)); // Initialization vector

        // Import AES key
        const aesCryptoKey = await window.crypto.subtle.importKey(
            'raw',
            aesKey.buffer,
            { name: 'AES-CBC', length: 256 },
            false,
            ['encrypt']
        );

        const encryptedFileChunks = [];
        let offset = 0;

        // Step 3: Encrypt file in chunks
        while (offset < file.size) {
            const chunk = file.slice(offset, offset + chunkSize);
            const chunkArrayBuffer = await readFileChunk(chunk);
            const encryptedChunk = await window.crypto.subtle.encrypt(
                { name: 'AES-CBC', iv },
                aesCryptoKey,
                chunkArrayBuffer
            );

            encryptedFileChunks.push(new Uint8Array(encryptedChunk)); // Store encrypted chunks
            offset += chunkSize;
        }

        // Concatenate all encrypted chunks into one ArrayBuffer
        const encryptedFile = mergeChunks(encryptedFileChunks);

        // Step 4: Encrypt AES key using the server's RSA public key
        const encryptedAesKey = await window.crypto.subtle.encrypt(
            { name: 'RSA-OAEP' },
            publicKey,
            aesKey.buffer
        );

        // Step 5: Prepare FormData with encoded data
        const formData = new FormData();
        formData.append('encrypted_data', base64js.fromByteArray(new Uint8Array(encryptedFile))); // Use base64-js
        formData.append('encrypted_aes_key', base64js.fromByteArray(new Uint8Array(encryptedAesKey)));
        formData.append('iv', base64js.fromByteArray(new Uint8Array(iv)));
        formData.append('file_extension', fileExtension);

        // Step 6: Send the upload request to the server
        const response = await fetch("/documents/upload", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'accept': 'application/json'
            },
            body: formData
        });

        if (!response.ok) throw new Error(await response.text());
        console.log(await response.json());

        loadingIndicator.classList.add('hidden');
        successMessage.classList.remove('hidden');
    } catch (error) {
        loadingIndicator.classList.add('hidden');
        alert('An error occurred while uploading the file: ' + error.message);
        console.error(error);
    } finally {
        fileInput.value = '';
    }
}

// Utility: Read a file chunk as ArrayBuffer
function readFileChunk(chunk) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = (e) => reject(e);
        reader.readAsArrayBuffer(chunk);
    });
}

// Utility: Merge encrypted chunks into a single ArrayBuffer
function mergeChunks(chunks) {
    const totalLength = chunks.reduce((sum, chunk) => sum + chunk.length, 0);
    const merged = new Uint8Array(totalLength);
    let offset = 0;

    chunks.forEach(chunk => {
        merged.set(chunk, offset);
        offset += chunk.length;
    });

    return merged.buffer;
}

// Utility: Convert PEM to ArrayBuffer
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

// // Utility function to convert PEM to ArrayBuffer
// function convertPemToArrayBuffer(pem) {
//     const pemHeader = "-----BEGIN PUBLIC KEY-----";
//     const pemFooter = "-----END PUBLIC KEY-----";
//     const pemContents = pem.replace(pemHeader, "").replace(pemFooter, "").replace(/\s+/g, "");
//     const binaryDerString = atob(pemContents);
//     const binaryDer = new Uint8Array(binaryDerString.length);
//     for (let i = 0; i < binaryDerString.length; i++) {
//         binaryDer[i] = binaryDerString.charCodeAt(i);
//     }
//     return binaryDer.buffer;
// }

// Utility function to convert ArrayBuffer to Base64
function arrayBufferToBase64(buffer) {
    const binary = String.fromCharCode.apply(null, new Uint8Array(buffer));
    return btoa(binary);
}

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