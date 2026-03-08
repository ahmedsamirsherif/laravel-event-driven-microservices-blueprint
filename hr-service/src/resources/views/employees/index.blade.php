@extends('layouts.challenge', [
    'title' => 'Employees · HR Service',
    'page' => 'employees-index',
])

@section('content')
    <section class="panel panel-soft">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Employees</h2>
            </div>
            <div class="toolbar">
                    {{-- Country toggle buttons rendered dynamically from /api/v1/countries --}}
                    <div class="toggle-group" id="country-toggle-container" aria-label="Country filter"></div>
                <a href="/employees/create" class="button-link">Create Employee</a>
            </div>
        </div>

        <div class="table-wrap">
            <div id="employee-flash" class="flash hidden"></div>
            <div id="employee-server-error" class="server-error hidden"></div>
            <div class="summary-line" id="employee-summary">Loading employees...</div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Salary</th>
                        <th>Country</th>
                        <th>Details</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="employee-rows">
                    <tr>
                        <td colspan="6" class="inline-message">Loading employees...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <div class="summary-line" id="employee-pagination-copy">Page 1 of 1</div>
            <div class="pagination-controls">
                <button type="button" class="button button-secondary" id="employees-prev">Prev</button>
                <span class="pagination-number" id="employees-page-label">1 / 1</span>
                <button type="button" class="button button-secondary" id="employees-next">Next</button>
            </div>
        </div>
    </section>

    <div class="fixed inset-0 z-50 modal-backdrop hidden" id="delete-modal" aria-hidden="true">
        <div class="modal-card">
            <h2>Delete Employee</h2>
            <p>Delete <strong id="delete-employee-name"></strong> from the HR service? This will also publish the delete event downstream.</p>
            <div class="modal-actions">
                <button type="button" class="button button-secondary" id="delete-cancel">Cancel</button>
                <button type="button" class="button button-danger" id="delete-confirm">Delete</button>
            </div>
        </div>
    </div>

    @push('scripts')
        <script id="page-config" type="application/json">{"mode":"index","perPage":15}</script>
        <script src="{{ asset('hr-ui.js') }}" defer></script>
    @endpush
@endsection