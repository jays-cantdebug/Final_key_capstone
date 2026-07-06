<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Psychometrician Dashboard
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow rounded-lg p-6">
                <h1 class="text-2xl font-bold mb-2">
                    Welcome, {{ auth()->user()->name }}
                </h1>

                <p class="text-gray-600">
                    Welcome to the Student Mental Health Assessment System.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-6">

                <div class="bg-blue-500 text-white rounded-lg p-6 shadow">
                    <h3 class="text-lg">Students</h3>
                    <p class="text-3xl font-bold">0</p>
                </div>

                <div class="bg-green-500 text-white rounded-lg p-6 shadow">
                    <h3 class="text-lg">Assessments</h3>
                    <p class="text-3xl font-bold">0</p>
                </div>

                <div class="bg-yellow-500 text-white rounded-lg p-6 shadow">
                    <h3 class="text-lg">Flagged Cases</h3>
                    <p class="text-3xl font-bold">0</p>
                </div>

                <div class="bg-red-500 text-white rounded-lg p-6 shadow">
                    <h3 class="text-lg">Counseling Sessions</h3>
                    <p class="text-3xl font-bold">0</p>
                </div>

            </div>

        </div>
    </div>
</x-app-layout>