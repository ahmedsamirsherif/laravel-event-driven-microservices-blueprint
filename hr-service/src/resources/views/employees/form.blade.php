@php
    $isEdit = $mode === 'edit';
@endphp

@extends('layouts.challenge', [
    'title' => ($isEdit ? 'Edit Employee' : 'Create Employee') . ' · HR Service',
    'page' => 'employee-form',
    'employeeId' => $employeeId,
])

@section('content')
    <section class="panel panel-soft form-panel">
        <div id="load-error" class="server-error hidden"></div>
        <div id="form-server-error" class="server-error hidden"></div>

        <h2 class="panel-title">{{ $isEdit ? 'Edit Employee' : 'Create Employee' }}</h2>

        <form id="employee-form" novalidate>
            <div class="grid grid-two" style="margin-top: 22px;">
                <div>
                    <label for="name">First name</label>
                    <input id="name" name="name" type="text" placeholder="John" autocomplete="given-name">
                    <div class="field-error" data-field-error="name"></div>
                </div>
                <div>
                    <label for="last_name">Last name</label>
                    <input id="last_name" name="last_name" type="text" placeholder="Doe" autocomplete="family-name">
                    <div class="field-error" data-field-error="last_name"></div>
                </div>
                <div>
                    <label for="salary">Salary</label>
                    <input id="salary" name="salary" type="number" min="0" step="0.01" placeholder="75000" inputmode="decimal">
                    <div class="field-error" data-field-error="salary"></div>
                </div>
                <div>
                    <label for="country">Country</label>
                    @if ($isEdit)
                        <input id="country_hidden" name="country" type="hidden" value="">
                        <div class="readonly-badge"><strong id="country-readonly-value">Loading...</strong> <span>(not editable)</span></div>
                    @else
                        <select id="country" name="country">
                            {{-- Options populated dynamically from /api/v1/countries --}}
                        </select>
                    @endif
                    <div class="field-error" data-field-error="country"></div>
                </div>
            </div>

            {{-- Country-specific fields rendered dynamically by hr-ui.js via /api/v1/schema/{country} --}}
            <div id="country-fields-container"></div>

            <div class="form-actions">
                <a href="/employees" class="button button-secondary">Cancel</a>
                <button type="submit" class="button" id="submit-button">{{ $isEdit ? 'Save Changes' : 'Create Employee' }}</button>
            </div>
        </form>
    </section>

    @push('scripts')
        <script id="page-config" type="application/json">@json([
            'mode' => $mode,
            'employeeId' => $employeeId,
        ])</script>
        <script src="{{ asset('hr-ui.js') }}" defer></script>
    @endpush
@endsection