@extends('layouts.print')

@section('content')
<div class="print-document max-w-3xl mx-auto p-8">
    <!-- Print Controls (hidden on print) -->
    <div class="no-print mb-6 flex justify-end space-x-3">
        <a href="{{ route('records.show', $record) }}" 
           class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
            Back to Record
        </a>
        <a href="{{ route('records.download', $record) }}" 
           class="px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
            Download Excel
        </a>
        <button onclick="window.print()"
                class="px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
            Print
        </button>
    </div>

    @if (session('success'))
        <div class="no-print mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
            {{ session('success') }}
        </div>
    @endif
    @if (session('generation_warnings'))
        <div class="no-print mb-6 bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded">
            <ul class="list-disc list-inside space-y-1">
                @foreach ((array) session('generation_warnings') as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (!empty($preview))
    <!-- Faithful render of the actual Excel template -->
    <div class="mb-8" style="position:relative;width:100%;max-width:900px;margin:0 auto;aspect-ratio:{{ $preview['width'] }} / {{ $preview['height'] }};background:#fff;box-shadow:0 1px 6px rgba(0,0,0,.15);overflow:hidden;">
        @if ($preview['background'])
            <img src="{{ $preview['background'] }}" alt="" style="position:absolute;left:{{ $preview['bgX'] / $preview['width'] * 100 }}%;top:{{ $preview['bgY'] / $preview['height'] * 100 }}%;width:{{ $preview['bgW'] / $preview['width'] * 100 }}%;height:{{ $preview['bgH'] / $preview['height'] * 100 }}%;">
        @endif
        @foreach ($preview['boxes'] as $box)
            @if ($box['kind'] === 'image')
                @if (!empty($box['src']))
                    <img src="{{ $box['src'] }}" alt="{{ $box['label'] }}" style="position:absolute;left:{{ $box['x'] / $preview['width'] * 100 }}%;top:{{ $box['y'] / $preview['height'] * 100 }}%;width:{{ $box['w'] / $preview['width'] * 100 }}%;height:{{ $box['h'] / $preview['height'] * 100 }}%;object-fit:fill;">
                @endif
            @else
                <div style="position:absolute;left:{{ $box['x'] / $preview['width'] * 100 }}%;top:{{ $box['y'] / $preview['height'] * 100 }}%;width:{{ $box['w'] / $preview['width'] * 100 }}%;height:{{ $box['h'] / $preview['height'] * 100 }}%;overflow:hidden;">
                    @foreach ($box['lines'] as $line)
                        <div style="text-align:{{ $line['align'] }};font-size:{{ $line['style']['size'] }}pt;{{ $line['style']['bold'] ? 'font-weight:bold;' : '' }}color:#{{ $line['style']['color'] }};line-height:1.25;white-space:pre-wrap;">{{ $line['text'] }}</div>
                    @endforeach
                </div>
            @endif
        @endforeach
    </div>
    @else
    <!-- Generic summary (cell-based templates) -->
    <!-- Document Header -->
    <div class="text-center mb-8 border-b-2 border-gray-800 pb-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ $record->template->name }}</h1>
        <p class="text-gray-600">Patient Empanelment Document</p>
    </div>

    <!-- Patient Information -->
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-300 pb-2">Patient Information</h2>
        
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-500">Patient Name</label>
                <p class="text-gray-900 font-medium">{{ $record->patient_name }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500">Birthdate</label>
                <p class="text-gray-900">{{ $record->birthdate?->format('m-d-y') ?? '' }}</p>
            </div>
            @if ($record->appointment_date)
            <div>
                <label class="block text-sm font-medium text-gray-500">Date of Appointment</label>
                <p class="text-gray-900">{{ $record->appointment_date->format('m-d-y') }}</p>
            </div>
            @endif
            @if ($record->auth_transaction_code)
            <div>
                <label class="block text-sm font-medium text-gray-500">Authorization Transaction Code</label>
                <p class="text-gray-900 font-mono">{{ $record->auth_transaction_code }}</p>
            </div>
            @endif
            @if ($record->pcu_error_code)
            <div>
                <label class="block text-sm font-medium text-gray-500">PCU Error Code</label>
                <p class="text-gray-900 font-mono">{{ $record->pcu_error_code }}</p>
            </div>
            @endif
            <div>
                <label class="block text-sm font-medium text-gray-500">PhilHealth ID</label>
                <p class="text-gray-900 font-mono">{{ $record->philhealth_id }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500">Head of Clinic</label>
                <p class="text-gray-900">{{ $record->head_of_clinic }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500">Date</label>
                <p class="text-gray-900">{{ $record->date_today ? $record->date_today->format('m-d-y') : now()->format('m-d-y') }}</p>
            </div>
        </div>
    </div>

    <!-- Images Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
        <div>
            <h3 class="text-md font-semibold text-gray-900 mb-3 border-b border-gray-300 pb-1">ID Image</h3>
            @if ($record->image_with_id_url)
                <img src="{{ $record->image_with_id_url }}" alt="ID Image" class="w-full max-h-64 object-contain border border-gray-300 rounded">
            @else
                <div class="w-full h-64 border-2 border-dashed border-gray-300 rounded flex items-center justify-center">
                    <span class="text-gray-500">No ID image available</span>
                </div>
            @endif
        </div>
        
        <div>
            <h3 class="text-md font-semibold text-gray-900 mb-3 border-b border-gray-300 pb-1">Empanelment Error Image</h3>
            @if ($record->empanelment_error_image_url)
                <img src="{{ $record->empanelment_error_image_url }}" alt="Empanelment Error" class="w-full max-h-64 object-contain border border-gray-300 rounded">
            @else
                <div class="w-full h-64 border-2 border-dashed border-gray-300 rounded flex items-center justify-center">
                    <span class="text-gray-500">No empanelment error image available</span>
                </div>
            @endif
        </div>
    </div>

    @endif
    <!-- End of generic summary -->

    <!-- Footer (screen only, never printed) -->
    <div class="no-print border-t border-gray-300 pt-4">
        <div class="flex justify-between items-center text-sm text-gray-500">
            <span>Generated: {{ now()->format('F j, Y g:i A') }}</span>
            <span>By: {{ $record->creator->name }}</span>
            <span>Record ID: {{ $record->id }}</span>
        </div>
    </div>
</div>
@endsection