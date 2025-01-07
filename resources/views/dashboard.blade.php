<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Home') }}
        </h2>
    </x-slot>

    <div class="p-6">
        @if(auth()->user()->role->title === 'user') <!-- Check if the role is 'User' -->
            <div>
                <a href="" class="bg-blue-500 text-white px-4 py-2 rounded">
                    Upload Document
                </a>
            </div>
        @elseif(auth()->user()->role->title === 'admin') <!-- Check if the role is 'Admin' -->
            <div class="mt-4">
                <label for="search_social_number" class="block text-sm font-medium text-gray-700">Search User's document by Social Number</label>
                <input type="text" id="search_social_number" name="search_social_number" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" placeholder="Enter Social Number">
                <button class="mt-2 bg-green-500 text-white px-4 py-2 rounded">Search</button>
            </div>
        @endif
    </div>
</x-app-layout>