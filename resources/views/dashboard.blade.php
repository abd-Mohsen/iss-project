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

            // Prepare the file and user ID
            const file = fileInput.files[0];
            const formData = new FormData();
            formData.append('file', file);
            formData.append('user_id', {{ auth()->id() }}); // Pass the authenticated user's ID

            try {
                // Send the upload request to the server
                const response = await fetch("{{ route('documents.upload') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: formData
                });

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
            } finally {
                // Reset the file input
                fileInput.value = '';
            }
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

    </script>
</x-app-layout>
